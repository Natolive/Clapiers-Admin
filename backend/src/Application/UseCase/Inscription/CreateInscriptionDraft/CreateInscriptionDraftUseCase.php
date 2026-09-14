<?php

namespace App\Application\UseCase\Inscription\CreateInscriptionDraft;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\InscriptionDraftPurger;
use App\Common\Service\InscriptionsStatusProvider;
use App\Common\Service\RecaptchaVerifier;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\InscriptionDraft;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ouvre un brouillon d'inscription et renvoie son token.
 *
 * C'est **ici** que le captcha est vérifié, et non à la soumission : le
 * brouillon accepte des fichiers, donc son ouverture est la porte d'entrée à
 * protéger. Le token qui en sort autorise ensuite tous les appels, comme
 * l'`accessToken` d'une licence pour le magic link.
 *
 * @extends AbstractUseCase<CreateInscriptionDraftCommand>
 */
class CreateInscriptionDraftUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RecaptchaVerifier $recaptchaVerifier,
        private readonly InscriptionsStatusProvider $inscriptionsStatus,
        private readonly InscriptionDraftPurger $purger,
    ) {
    }

    public function run(?CommandInterface $command = null): InscriptionDraft
    {
        if (!$command instanceof CreateInscriptionDraftCommand) {
            throw new UseCaseException('Invalid command');
        }

        if (!$this->inscriptionsStatus->isFormOpen()) {
            throw new UseCaseException(
                'Les inscriptions en ligne sont fermées pour le moment.',
                Response::HTTP_FORBIDDEN,
            );
        }

        if (!$this->recaptchaVerifier->verify((string) $command->recaptchaToken)) {
            throw new UseCaseException('Veuillez valider le captcha.');
        }

        $draft = new InscriptionDraft();
        $this->entityManager->persist($draft);
        $this->entityManager->flush();

        // Ménage au fil de l'eau, une fois par process : les brouillons
        // abandonnés emportent leurs fichiers, et il n'y a pas de cron à
        // planifier (même parti pris que la purge des logs).
        $this->purger->purgeOnce();

        return $draft;
    }
}
