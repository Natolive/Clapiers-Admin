<?php

namespace App\Application\UseCase\Inscription\SaveInscriptionDraft;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\InscriptionDraft;
use App\Repository\InscriptionDraftRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enregistre les champs saisis, à chaque changement d'étape du formulaire, pour
 * que la personne retrouve son wizard après une déconnexion.
 *
 * @extends AbstractUseCase<SaveInscriptionDraftCommand>
 */
class SaveInscriptionDraftUseCase extends AbstractUseCase
{
    /**
     * Le brouillon est écrit par un appel public non authentifié : on n'y
     * accepte que des scalaires, en nombre et en longueur bornés. Il ne sert
     * qu'à réafficher le formulaire — la validation, elle, reste portée par
     * `SubmitLicenseRequestCommand` sur le corps envoyé à la soumission.
     */
    private const MAX_FIELDS = 40;
    private const MAX_VALUE_LENGTH = 500;

    public function __construct(
        private readonly InscriptionDraftRepository $drafts,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function run(?CommandInterface $command = null): InscriptionDraft
    {
        if (!$command instanceof SaveInscriptionDraftCommand) {
            throw new UseCaseException('Invalid command');
        }

        $draft = $this->drafts->findOneByToken($command->token);
        if (!$draft) {
            throw new UseCaseException('Brouillon introuvable', Response::HTTP_NOT_FOUND);
        }

        $draft->setPayload($this->sanitize($command->payload));
        $this->entityManager->flush();

        return $draft;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function sanitize(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            if (\count($clean) >= self::MAX_FIELDS) {
                break;
            }
            if ($value !== null && !is_scalar($value)) {
                continue;
            }
            $clean[(string) $key] = \is_string($value) ? mb_substr($value, 0, self::MAX_VALUE_LENGTH) : $value;
        }

        return $clean;
    }
}
