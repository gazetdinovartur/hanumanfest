<?php

namespace App\Service;

use App\DTO\CalculatePriceRequest;
use App\DTO\CreateApplicationRequest;
use App\Entity\Application;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Infrastructure\GoogleSheets\GoogleSheetsExportService;
use App\Repository\ApplicationRepository;
use App\Repository\UserRepository;
use App\Util\PhoneNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ApplicationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly ApplicationRepository $applicationRepository,
        private readonly FestivalPricingCalculator $pricingCalculator,
        private readonly GoogleSheetsExportService $googleSheetsExportService,
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

        $user = $this->findOrCreateUser($request->name, $email, $phone);

        $duplicate = $this->applicationRepository->findActiveDuplicateByEmail(
            $email,
            $pricingContext->product,
            $pricingContext->pricingPeriod->getSeason()
                ?? throw new BadRequestHttpException('Pricing period has no season'),
        );
        if ($duplicate) {
            throw new ConflictHttpException(sprintf(
                'Active application already exists: %s',
                $duplicate->getUuid(),
            ));
        }

        $payNowAmount = $pricingContext->result->payNowAmount;

        $application = new Application();
        $application->setUser($user);
        $application->setProduct($pricingContext->product);
        $application->setPricingPeriod($pricingContext->pricingPeriod);
        $application->setSeason($pricingContext->pricingPeriod->getSeason());
        $application->setStatus(ApplicationStatus::New);
        $application->setTotalAmount($pricingContext->result->totalAmount);
        $application->setPaidAmount(0);
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

        $this->googleSheetsExportService->exportApplication($application);

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
}
