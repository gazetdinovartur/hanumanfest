<?php

namespace App\Service;

use App\DTO\CalculatePriceRequest;
use App\DTO\CreateApplicationRequest;
use App\Entity\Application;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Exception\DuplicateApplicationException;
use App\Infrastructure\GoogleSheets\GoogleSheetsExportService;
use App\Repository\ApplicationRepository;
use App\Repository\UserRepository;
use App\Util\PhoneNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ApplicationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly ApplicationRepository $applicationRepository,
        private readonly FestivalPricingCalculator $pricingCalculator,
        private readonly GoogleSheetsExportService $googleSheetsExportService,
        private readonly RegistrationTestMode $registrationTestMode,
        private readonly PaymentLinkService $paymentLinkService,
        private readonly AfterResponseWork $afterResponseWork,
    ) {
    }

    public function create(CreateApplicationRequest $request): Application
    {
        $email = filter_var($request->email, FILTER_VALIDATE_EMAIL);
        $phone = PhoneNormalizer::toE164($request->phone);

        if (!$email || !$request->name || !$phone) {
            throw new BadRequestHttpException('Invalid name, email or phone');
        }

        $pricingContext = $this->pricingCalculator->calculateWithContext(
            new CalculatePriceRequest(
                participationOptionId: $request->participationOptionId,
                adultsCount: $request->adultsCount,
                childrenCount: $request->childrenCount,
                transferIncluded: $request->transferIncluded,
                paymentFactor: $request->paymentFactor,
            ),
        );

        $isTest = $this->registrationTestMode->isEnabled();
        $duplicate = $this->applicationRepository->findActiveDuplicateByEmail(
            $email,
            $pricingContext->product,
            $pricingContext->pricingPeriod->getSeason()
                ?? throw new BadRequestHttpException('Pricing period has no season'),
            $isTest,
        );
        if ($duplicate) {
            $payUrl = null;
            $token = null;
            if ($duplicate->getRemainingAmount() > 0) {
                $link = $this->paymentLinkService->ensureForPartialApplication($duplicate);
                if ($link) {
                    $payUrl = $this->paymentLinkService->publicPayUrl($link);
                    $token = $link->getToken();
                }
            }

            throw new DuplicateApplicationException($duplicate, $payUrl, $token);
        }

        $user = $this->findOrCreateUser($request->name, $email, $phone);

        $payNowAmount = $pricingContext->result->payNowAmount;

        $application = new Application();
        $application->setUser($user);
        $application->setProduct($pricingContext->product);
        $application->setPricingPeriod($pricingContext->pricingPeriod);
        $application->setSeason($pricingContext->pricingPeriod->getSeason());
        $application->setStatus(ApplicationStatus::New);
        $application->setTotalAmount($pricingContext->result->totalAmount);
        $application->setPaidAmount(0);
        $application->setIsTest($isTest);
        $payload = array_merge($request->payload, [
            'participationOptionId' => $pricingContext->participationOption->getId(),
            'participationOptionCode' => $pricingContext->participationOption->getCode(),
            'participationOptionName' => $pricingContext->participationOption->getName(),
            'pricingPeriodName' => $pricingContext->pricingPeriod->getName(),
            'adultsCount' => max(1, $request->adultsCount),
            'childrenCount' => max(0, $request->childrenCount),
            'transferIncluded' => $request->transferIncluded,
            'paymentFactor' => $request->paymentFactor,
            'payNowAmount' => $payNowAmount,
        ]);

        $optionCode = $pricingContext->participationOption->getCode();
        $isOurTent = str_starts_with($optionCode, 'OUR_TENT');
        if (!$isOurTent) {
            unset($payload['tentRoommate']);
        } elseif (isset($payload['tentRoommate'])) {
            $roommate = trim((string) $payload['tentRoommate']);
            if ('' === $roommate) {
                unset($payload['tentRoommate']);
            } else {
                $payload['tentRoommate'] = $roommate;
            }
        }

        $application->setPayload($payload);

        $this->entityManager->persist($application);
        $this->entityManager->flush();

        $applicationId = $application->getId();
        $this->afterResponseWork->add(function () use ($applicationId): void {
            if ($applicationId === null) {
                return;
            }
            $stored = $this->applicationRepository->find($applicationId);
            if ($stored instanceof Application) {
                $this->googleSheetsExportService->exportApplication($stored);
            }
        });

        return $application;
    }

    private function findOrCreateUser(string $name, string $email, string $phone): User
    {
        $user = $this->userRepository->findOneByEmail($email);

        if ($user) {
            $user->setName($name);
            $user->setPhone($phone);

            return $user;
        }

        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setPhone($phone);

        $this->entityManager->persist($user);

        return $user;
    }

    public function cancelUnpaidByPaymentToken(string $token): void
    {
        $token = trim($token);
        if ($token === '') {
            throw new BadRequestHttpException('Не найден токен заявки.');
        }

        $link = $this->paymentLinkService->findByToken($token);
        $application = $link?->getApplication();
        if (!$link || !$application) {
            throw new NotFoundHttpException('Заявка не найдена.');
        }

        if ($application->getStatus() === ApplicationStatus::Cancelled) {
            return;
        }

        if ($application->getPaidAmount() > 0 || $application->getStatus() !== ApplicationStatus::New) {
            throw new BadRequestHttpException('Нельзя отменить заявку с оплатой. Если нужна помощь — напишите нам.');
        }

        $application->setStatus(ApplicationStatus::Cancelled);
        $this->entityManager->flush();
    }
}
