<?php

namespace App\Admin;

use App\Service\Content\UploadPathNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use Symfony\Component\Form\FormBuilderInterface;

abstract class AbstractUploadCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UploadPathNormalizer $uploadPathNormalizer,
    ) {
    }

    /** @return array<string, string> property name => uploads subdirectory */
    abstract protected function uploadPathMap(): array;

    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        return $this->buildUploadForm($entityDto, $formOptions, $context);
    }

    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        return $this->buildUploadForm($entityDto, $formOptions, $context, isNew: true);
    }

    private function buildUploadForm(
        EntityDto $entityDto,
        KeyValueStore $formOptions,
        AdminContext $context,
        bool $isNew = false,
    ): FormBuilderInterface {
        $entity = $entityDto->getInstance();
        $this->uploadPathNormalizer->stripForForm($entity, $this->uploadPathMap());

        return $isNew
            ? parent::createNewFormBuilder($entityDto, $formOptions, $context)
            : parent::createEditFormBuilder($entityDto, $formOptions, $context);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->uploadPathNormalizer->expandForStorage($entityInstance, $this->uploadPathMap());
        parent::persistEntity($entityManager, $entityInstance);
        $this->uploadPathNormalizer->reset($entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->uploadPathNormalizer->expandForStorage($entityInstance, $this->uploadPathMap());
        parent::updateEntity($entityManager, $entityInstance);
        $this->uploadPathNormalizer->reset($entityInstance);
    }
}
