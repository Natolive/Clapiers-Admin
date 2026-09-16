<?php

namespace App\Application\UseCase\Member\ExportMembers;

use App\Entity\Enum\LicenseStatus;
use App\Entity\License;
use App\Entity\Member;
use App\Entity\Team;

/**
 * Colonnes proposées à l'export des licenciés : une case = une colonne du
 * fichier. L'enum est la source unique — l'en-tête, la valeur d'une ligne et la
 * liste des choix valides côté validation en sortent tous, donc ajouter une
 * colonne se fait ici et nulle part ailleurs.
 */
enum MemberExportColumn: string
{
    // Identité
    case LAST_NAME = 'lastName';
    case FIRST_NAME = 'firstName';
    case GENDER = 'gender';
    case BIRTH_DATE = 'birthDate';
    case AGE = 'age';
    case NATIONALITY = 'nationality';
    case STATUS = 'status';

    // Contact
    case EMAIL = 'email';
    case PHONE_NUMBER = 'phoneNumber';

    // Adresse
    case ADDRESS_STREET = 'addressStreet';
    case ADDRESS_ZIP = 'addressZip';
    case ADDRESS_CITY = 'addressCity';

    // Équipes
    case TEAMS = 'teams';

    // Licence de la saison exportée
    case LICENSE_NUMBER = 'licenseNumber';
    case FSGT_REGISTERED = 'fsgtRegistered';
    case FSGT_REGISTERED_AT = 'fsgtRegisteredAt';
    case LICENSE_STATUS = 'licenseStatus';
    case LICENSE_PAID = 'licensePaid';
    case LICENSE_AMOUNT = 'licenseAmount';
    case LICENSE_APPROVED_AT = 'licenseApprovedAt';
    case HEALTH_DECLARATION = 'healthDeclaration';

    // Représentant légal (mineur)
    case LEGAL_REP_FIRST_NAME = 'legalRepFirstName';
    case LEGAL_REP_LAST_NAME = 'legalRepLastName';
    case LEGAL_REP_EMAIL = 'legalRepEmail';
    case LEGAL_REP_PHONE = 'legalRepPhone';

    // Suivi
    case CREATED_AT = 'createdAt';

    /** En-tête de la colonne dans le fichier. */
    public function label(): string
    {
        return match ($this) {
            self::LAST_NAME => 'Nom',
            self::FIRST_NAME => 'Prénom',
            self::GENDER => 'Genre',
            self::BIRTH_DATE => 'Date de naissance',
            self::AGE => 'Âge',
            self::NATIONALITY => 'Nationalité',
            self::STATUS => 'Statut du membre',
            self::EMAIL => 'Email',
            self::PHONE_NUMBER => 'Téléphone',
            self::ADDRESS_STREET => 'Adresse',
            self::ADDRESS_ZIP => 'Code postal',
            self::ADDRESS_CITY => 'Ville',
            self::TEAMS => 'Équipes',
            self::LICENSE_NUMBER => 'N° de licence',
            self::FSGT_REGISTERED => 'Inscrit FSGT',
            self::FSGT_REGISTERED_AT => 'Inscrit FSGT le',
            self::LICENSE_STATUS => 'Statut de la licence',
            self::LICENSE_PAID => 'Licence payée',
            self::LICENSE_AMOUNT => 'Montant (€)',
            self::LICENSE_APPROVED_AT => 'Validée le',
            self::HEALTH_DECLARATION => 'Attestation santé',
            self::LEGAL_REP_FIRST_NAME => 'Représentant légal - prénom',
            self::LEGAL_REP_LAST_NAME => 'Représentant légal - nom',
            self::LEGAL_REP_EMAIL => 'Représentant légal - email',
            self::LEGAL_REP_PHONE => 'Représentant légal - téléphone',
            self::CREATED_AT => 'Créé le',
        };
    }

    /**
     * Valeur de la cellule. Les montants et l'âge sortent en nombre (une somme
     * Excel doit marcher), tout le reste en texte lisible ; les dates en
     * jj/mm/aaaa. `$license` est la licence de la saison exportée, absente si le
     * membre n'en a pas (filtre « toutes saisons »).
     */
    public function value(Member $member, ?License $license): string|int|float|null
    {
        return match ($this) {
            self::LAST_NAME => $member->getLastName(),
            self::FIRST_NAME => $member->getFirstName(),
            self::GENDER => $member->getGender()->label(),
            self::BIRTH_DATE => $member->getBirthDate()->format('d/m/Y'),
            self::AGE => $member->getBirthDate()->diff(new \DateTimeImmutable('today'))->y,
            self::NATIONALITY => $member->getNationality(),
            self::STATUS => $member->getStatus()->label(),
            self::EMAIL => $member->getEmail(),
            self::PHONE_NUMBER => $member->getPhoneNumber(),
            self::ADDRESS_STREET => $member->getAddress()->street,
            self::ADDRESS_ZIP => $member->getAddress()->zip,
            self::ADDRESS_CITY => $member->getAddress()->city,
            self::TEAMS => implode(', ', array_map(
                static fn (Team $team) => $team->getName(),
                $member->getTeams()->toArray(),
            )),
            // Le n° porté par la licence de la saison fait foi ; celui de la
            // fiche membre n'est qu'un repli pour les fiches saisies à la main.
            self::LICENSE_NUMBER => $license?->getLicenseNumber() ?? $member->getLicenseNumber() ?? '',
            self::FSGT_REGISTERED => self::yesNo($license?->isFsgtRegistered() ?? false),
            self::FSGT_REGISTERED_AT => $license?->getFsgtRegisteredAt()?->format('d/m/Y') ?? '',
            self::LICENSE_STATUS => $license?->getStatus()->label() ?? '',
            self::LICENSE_PAID => self::yesNo($license?->getStatus() === LicenseStatus::PAYEE),
            // Stocké en centimes ; l'export sort des euros pour être sommable.
            self::LICENSE_AMOUNT => $license?->getAmount() !== null ? $license->getAmount() / 100 : null,
            self::LICENSE_APPROVED_AT => $license?->getApprovedAt()?->format('d/m/Y') ?? '',
            self::HEALTH_DECLARATION => $license?->getHealthDeclaration() === null
                ? ''
                : self::yesNo($license->getHealthDeclaration()),
            self::LEGAL_REP_FIRST_NAME => $member->getLegalRepresentative()->firstName,
            self::LEGAL_REP_LAST_NAME => $member->getLegalRepresentative()->lastName,
            self::LEGAL_REP_EMAIL => $member->getLegalRepresentative()->email,
            self::LEGAL_REP_PHONE => $member->getLegalRepresentative()->phone,
            self::CREATED_AT => $member->getCreatedAt()?->format('d/m/Y') ?? '',
        };
    }

    private static function yesNo(bool $value): string
    {
        return $value ? 'Oui' : 'Non';
    }

    /**
     * Toutes les colonnes, dans l'ordre de déclaration : c'est l'export par
     * défaut, quand le client n'en choisit aucune.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * Cible du `Assert\Choice` sur la commande.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
