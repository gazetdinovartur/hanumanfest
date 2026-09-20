<?php

namespace App\Entity;

use App\Repository\FestivalSeasonRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FestivalSeasonRepository::class)]
#[ORM\Table(name: 'festival_season')]
#[ORM\UniqueConstraint(name: 'uniq_festival_season_year', columns: ['year'])]
#[UniqueEntity(fields: ['year'], message: 'Сезон с таким годом уже есть.')]
class FestivalSeason
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\Range(min: 2020, max: 2100, notInRangeMessage: 'Укажите год фестиваля.')]
    private int $year = 0;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Укажите название сезона.')]
    private string $name = '';

    #[ORM\Column]
    private bool $isCurrent = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isCurrent(): bool
    {
        return $this->isCurrent;
    }

    public function setIsCurrent(bool $isCurrent): static
    {
        $this->isCurrent = $isCurrent;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : (string) $this->year;
    }
}
