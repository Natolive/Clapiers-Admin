<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Repository\InscriptionDraftRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Brouillon d'inscription publique : ce que la personne a saisi et les pièces
 * qu'elle a déjà déposées, **avant** que la demande de licence n'existe.
 *
 * Répond à deux problèmes du même coup :
 *  - les pièces partent dès qu'elles sont choisies, donc une demande ne peut
 *    plus naître amputée. L'inverse arrivait : les quatre fichiers ne partaient
 *    qu'après la validation, et tout ce qui cassait pendant cette rafale
 *    (413 de l'ingress, coupure mobile, onglet en veille) laissait une demande
 *    complète côté informations mais sans ses pièces ;
 *  - le `token` permet de reprendre l'inscription après une déconnexion ou
 *    depuis un autre onglet. C'est le seul secret qui autorise les appels sur
 *    le brouillon, exactement comme l'`accessToken` d'une licence pour le
 *    magic link — d'où le même format (32 octets aléatoires en hexa).
 *
 * Les fichiers vivent sur Bunny sous `drafts/<token>/`, et sont recopiés dans
 * la médiathèque du membre à la validation. Le brouillon est supprimé aussitôt,
 * et purgé au bout de 30 jours d'inactivité : ces données personnelles ne
 * survivent pas à l'inscription.
 *
 * @phpstan-type DraftDocument array{storedName: string, originalName: string, mimeType: ?string, size: ?int}
 */
#[ORM\Entity(repositoryClass: InscriptionDraftRepository::class)]
#[ORM\Table(name: 'inscription_draft')]
#[ORM\UniqueConstraint(name: 'uniq_inscription_draft_token', columns: ['token'])]
#[ORM\Index(name: 'idx_inscription_draft_updated_at', columns: ['updated_at'])]
class InscriptionDraft
{
    use IdTrait;

    /** Racine des fichiers de brouillon dans la zone Bunny. */
    public const STORAGE_PREFIX = 'drafts';

    #[ORM\Column(length: 64)]
    private string $token;

    /**
     * Champs du formulaire tels que saisis. Ne sert QUE à réafficher le wizard
     * à la reprise : la validation à la soumission reste portée par les
     * contraintes de `SubmitLicenseRequestCommand`, sur le corps envoyé alors.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    /** @var array<string, DraftDocument> systemKey => métadonnées du fichier déposé */
    #[ORM\Column(type: Types::JSON)]
    private array $documents = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** Dernière activité : c'est elle que la purge regarde, pas la création. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->token = bin2hex(random_bytes(32));
        $this->createdAt = new \DateTimeImmutable('now');
        $this->updatedAt = $this->createdAt;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /** @param array<string, mixed> $payload */
    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this->touch();
    }

    /** @return array<string, DraftDocument> */
    public function getDocuments(): array
    {
        return $this->documents;
    }

    /**
     * Enregistre (ou remplace) la pièce d'un slot et renvoie le `storedName`
     * qu'elle remplace, s'il y en avait un. L'appelant le supprime de Bunny
     * **après** son flush : effacer avant laisserait le brouillon pointer sur
     * un objet déjà disparu si l'écriture échouait.
     *
     * @param DraftDocument $meta
     */
    public function putDocument(string $systemKey, array $meta): ?string
    {
        $previous = $this->documents[$systemKey]['storedName'] ?? null;
        $this->documents[$systemKey] = $meta;
        $this->touch();

        return $previous;
    }

    /** Retire la pièce d'un slot et renvoie son `storedName` (même règle d'ordre). */
    public function removeDocument(string $systemKey): ?string
    {
        $previous = $this->documents[$systemKey]['storedName'] ?? null;
        unset($this->documents[$systemKey]);
        $this->touch();

        return $previous;
    }

    /**
     * Tous les objets Bunny du brouillon — ce qu'il faut supprimer quand il
     * disparaît (validation, abandon, purge).
     *
     * @return string[]
     */
    public function storedNames(): array
    {
        return array_values(array_map(fn (array $meta) => $meta['storedName'], $this->documents));
    }

    /** Dossier des fichiers de ce brouillon dans la zone. */
    public function storagePrefix(): string
    {
        return self::STORAGE_PREFIX.'/'.$this->token;
    }

    /**
     * Vue publique de reprise. Le `storedName` n'en fait jamais partie : le
     * client n'a aucune raison de connaître le chemin des objets.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'payload' => $this->payload,
            'documents' => array_map(fn (array $meta) => [
                'originalName' => $meta['originalName'],
                'mimeType' => $meta['mimeType'] ?? null,
                'size' => $meta['size'] ?? null,
            ], $this->documents),
        ];
    }

    private function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable('now');

        return $this;
    }
}
