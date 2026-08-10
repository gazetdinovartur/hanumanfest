<?php

namespace App\Admin;

use App\Entity\Person;
use App\Enum\PersonKind;
use Doctrine\ORM\EntityManagerInterface;

final class MasterPersonCrudController extends AbstractPersonCrudController
{
    protected function sortableKind(): string
    {
        return 'person-master';
    }

    /** @return list<PersonKind> */
    protected function personKinds(): array
    {
        return [PersonKind::Master];
    }

    protected function sectionLabelSingular(): string
    {
        return 'Мастер';
    }

    protected function sectionLabelPlural(): string
    {
        return 'Мастера и практики';
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->ensureMasterKind($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->ensureMasterKind($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function ensureMasterKind(object $entityInstance): void
    {
        if ($entityInstance instanceof Person) {
            $entityInstance->setKind(PersonKind::Master);
        }
    }
}
