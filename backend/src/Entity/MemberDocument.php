<?php

namespace App\Entity;

use App\Entity\Enum\MemberDocumentType;
use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampableTrait;
use App\Repository\MemberDocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Nœud de la médiathèque d'un membre. Arbre auto-référencé : le premier niveau
 * est la saison (dossier), qui contient un mapping de documents par défaut plus
 * tout dossier/document ajouté librement par l'administration.
 */
#[ORM\Entity(repositoryClass: MemberDocumentRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['member_id'], name: 'idx_member_document_member')]
class MemberDocument
{
    use IdTrait;
    use TimestampableTrait;

    /**
     * Identifiant public non énumérable : c'est lui (et non l'id auto-incrémenté)
     * qui circule dans les URLs / le front, pour ne pas exposer les documents.
     */
    #[ORM\Column(length: 36, unique: true)]
    private string $uuid;

    #[ORM\ManyToOne(targetEntity: Member::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Member $member;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?MemberDocument $parent = null;

    /**
     * @var Collection<int, MemberDocument>
     */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $children;

    #[ORM\Column(length: 20, enumType: MemberDocumentType::class)]
    private MemberDocumentType $type;

    #[ORM\Column(length: 255)]
    private string $name;

    /** Saison portée par les dossiers de premier niveau (null sinon). */
    #[ORM\Column(length: 9, nullable: true)]
    private ?string $season = null;

    /** Clé système d'un nœud par défaut (season, license, medical_certificate). */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $systemKey = null;

    /** Nœud par défaut : ni renommable ni supprimable (son fichier reste gérable). */
    #[ORM\Column(options: ['default' => false])]
    private bool $protected = false;

    // ── Métadonnées fichier (uniquement pour un DOCUMENT rempli) ──────────────

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $storedName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(nullable: true)]
    private ?int $size = null;

    public function __construct()
    {
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->children = new ArrayCollection();
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getMember(): Member
    {
        return $this->member;
    }

    public function setMember(Member $member): static
    {
        $this->member = $member;

        return $this;
    }

    public function getParent(): ?MemberDocument
    {
        return $this->parent;
    }

    public function setParent(?MemberDocument $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, MemberDocument>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function getType(): MemberDocumentType
    {
        return $this->type;
    }

    public function setType(MemberDocumentType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isFolder(): bool
    {
        return $this->type === MemberDocumentType::FOLDER;
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

    public function getSeason(): ?string
    {
        return $this->season;
    }

    public function setSeason(?string $season): static
    {
        $this->season = $season;

        return $this;
    }

    public function getSystemKey(): ?string
    {
        return $this->systemKey;
    }

    public function setSystemKey(?string $systemKey): static
    {
        $this->systemKey = $systemKey;

        return $this;
    }

    public function isProtected(): bool
    {
        return $this->protected;
    }

    public function setProtected(bool $protected): static
    {
        $this->protected = $protected;

        return $this;
    }

    public function getStoredName(): ?string
    {
        return $this->storedName;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function hasFile(): bool
    {
        return $this->storedName !== null;
    }

    /** Attache (ou remplace) les métadonnées de fichier sur un document. */
    public function setFile(string $storedName, string $originalName, ?string $mimeType, ?int $size): static
    {
        $this->storedName = $storedName;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->size = $size;

        return $this;
    }

    /** Détache le fichier (le nœud document reste, vide). */
    public function clearFile(): static
    {
        $this->storedName = null;
        $this->originalName = null;
        $this->mimeType = null;
        $this->size = null;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getUuid(),
            'parentId' => $this->getParent()?->getUuid(),
            'type' => $this->getType()->value,
            'name' => $this->getName(),
            'season' => $this->getSeason(),
            'systemKey' => $this->getSystemKey(),
            'protected' => $this->isProtected(),
            'hasFile' => $this->hasFile(),
            'originalName' => $this->getOriginalName(),
            'mimeType' => $this->getMimeType(),
            'size' => $this->getSize(),
            'children' => $this->sortedChildren(),
            'createdAt' => $this->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $this->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }

    /** Dossiers avant documents, puis par nom (ordre stable pour l'UI). */
    private function sortedChildren(): array
    {
        $children = $this->getChildren()->toArray();

        usort($children, static function (MemberDocument $a, MemberDocument $b): int {
            if ($a->isFolder() !== $b->isFolder()) {
                return $a->isFolder() ? -1 : 1;
            }

            return strcasecmp($a->getName(), $b->getName());
        });

        return array_map(static fn (MemberDocument $node) => $node->toArray(), $children);
    }
}
