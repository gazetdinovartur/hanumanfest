<?php

namespace App\Admin;

use App\Entity\Person;
use App\Enum\PersonKind;
use Doctrine\ORM\EntityManagerInterface;

final class MusicianPersonCrudController extends AbstractPersonCrudController
{
    protected function sortableKind(): string
    {
        return 'person-musician';
    }

    /** @return list<PersonKind> */
    protected function personKinds(): array
    {
        return [PersonKind::Musician];
    }

    protected function sectionLabelSingular(): string
    {
        return 'Музыкант';
    }

    protected function sectionLabelPlural(): string
    {
        return 'Музыканты';
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
            $entityInstance->setKind(PersonKind::Musician);
        }
    }
}
