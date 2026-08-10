<?php

namespace App\Admin;

use App\Entity\Person;
use App\Enum\PersonKind;
use Doctrine\ORM\EntityManagerInterface;

final class GuestPersonCrudController extends AbstractPersonCrudController
{
    protected function sortableKind(): string
    {
        return 'person-guest';
    }

    /** @return list<PersonKind> */
    protected function personKinds(): array
    {
        return [PersonKind::Guest];
    }

    protected function sectionLabelSingular(): string
    {
        return 'Специальный гость';
    }

    protected function sectionLabelPlural(): string
    {
        return 'Специальные гости';
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->ensureKind($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->ensureKind($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function ensureKind(object $entityInstance): void
    {
        if ($entityInstance instanceof Person) {
            $entityInstance->setKind(PersonKind::Guest);
        }
    }
}
