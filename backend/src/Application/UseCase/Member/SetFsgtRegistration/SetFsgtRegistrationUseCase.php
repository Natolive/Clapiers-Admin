<?php

namespace App\Application\UseCase\Member\SetFsgtRegistration;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\License;
use App\Repository\LicenseRepository;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Déclare (ou retire) l'inscription d'un licencié à la FSGT pour une saison.
 * L'information vit sur la licence de la saison, pas sur le membre : on est
 * inscrit pour une saison donnée, et l'an prochain tout est à refaire.
 *
 * Cocher exige un numéro de licence — c'est la fédération qui l'attribue, et
 * une inscription sans numéro n'est pas vérifiable. Décocher garde le numéro :
 * corriger une erreur de saisie ne doit pas effacer une donnée utile.
 *
 * @extends AbstractUseCase<SetFsgtRegistrationCommand>
 */
class SetFsgtRegistrationUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly LicenseRepository $licenseRepository,
        private readonly SeasonProvider $seasonProvider,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof SetFsgtRegistrationCommand) {
            throw new UseCaseException('Invalid command');
        }

        $member = $this->memberRepository->find($command->memberId);
        if ($member === null) {
            throw new UseCaseException('Member not found', Response::HTTP_NOT_FOUND);
        }

        $season = $command->season ?: $this->seasonProvider->current();

        $license = $this->licenseRepository->findOneByMemberAndSeason($member, $season);
        if ($license === null) {
            throw new UseCaseException(
                sprintf('Ce licencié n\'a pas de licence pour la saison %s.', $season),
                Response::HTTP_NOT_FOUND,
            );
        }

        if ($command->registered) {
            $number = trim((string) $command->licenseNumber);
            if ($number === '') {
                throw new UseCaseException(
                    'Le numéro de licence est requis pour déclarer une inscription FSGT.',
                    Response::HTTP_BAD_REQUEST,
                );
            }

            // Le numéro est attribué à la personne et la suit d'une saison à
            // l'autre : il vit sur le membre, seule la date d'inscription est
            // saisonnière.
            $member->setLicenseNumber($number);
            $license->setFsgtRegisteredAt(new \DateTimeImmutable('now'));
        } else {
            $license->setFsgtRegisteredAt(null);
        }

        $this->em->flush();

        return $this->payload($license);
    }

    /** @return array<string, mixed> */
    private function payload(License $license): array
    {
        return [
            'memberId' => $license->getMember()->getId(),
            'season' => $license->getSeason(),
            'fsgtRegistered' => $license->isFsgtRegistered(),
            'fsgtRegisteredAt' => $license->getFsgtRegisteredAt()?->format(DATE_ATOM),
            'licenseNumber' => $license->getMember()->getLicenseNumber(),
        ];
    }
}
