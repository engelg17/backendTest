<?php

namespace App\Entity;

use App\Repository\FilmRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FilmRepository::class)]
class Film
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $title;

    #[ORM\ManyToMany(targetEntity: Actor::class, inversedBy: 'films')]
    private $actors;

    #[ORM\ManyToMany(targetEntity: Director::class, inversedBy: 'films')]
    private $directors;

    #[ORM\Column(type: 'string', length: 255)]
    private $production_company;

    #[ORM\Column(type: 'string', length: 255)]
    private $genre;

    #[ORM\Column(type: 'string', length: 255)]
    private $published_on;

    #[ORM\Column(type: 'integer')]
    private $duration;

    public function __construct()
    {
        $this->actors = new ArrayCollection();
        $this->directors = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }
    
    /**
     * @return Collection<int, actor>
     */
    public function getActors(): Collection
    {
        return $this->actors;
    }

    public function addActor(actor $actor): self
    {
        if (!$this->actors->contains($actor)) {
            $this->actors[] = $actor;
        }

        return $this;
    }

    public function removeActor(actor $actor): self
    {
        $this->actors->removeElement($actor);

        return $this;
    }

    /**
     * @return Collection<int, director>
     */
    public function getDirectors(): Collection
    {
        return $this->directors;
    }

    public function addDirector(director $director): self
    {
        if (!$this->directors->contains($director)) {
            $this->directors[] = $director;
        }

        return $this;
    }

    public function removeDirector(director $director): self
    {
        $this->directors->removeElement($director);

        return $this;
    }

    public function getProductionCompany(): ?string
    {
        return $this->production_company;
    }

    public function setProductionCompany(string $production_company): self
    {
        $this->production_company = $production_company;

        return $this;
    }

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function setGenre(string $genre): self
    {
        $this->genre = $genre;

        return $this;
    }

    public function getPublishedOn(): ?string
    {
        return $this->published_on;
    }

    public function setPublishedOn(string $published_on): self
    {
        $this->published_on = $published_on;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): self
    {
        $this->duration = $duration;

        return $this;
    }
}
