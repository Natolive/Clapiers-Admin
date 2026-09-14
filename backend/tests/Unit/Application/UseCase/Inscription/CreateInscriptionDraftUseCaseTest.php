<?php

namespace App\Tests\Unit\Application\UseCase\Inscription;

use App\Application\UseCase\Inscription\CreateInscriptionDraft\CreateInscriptionDraftCommand;
use App\Application\UseCase\Inscription\CreateInscriptionDraft\CreateInscriptionDraftUseCase;
use App\Common\Exception\UseCaseException;
use App\Common\Service\InscriptionDraftPurger;
use App\Common\Service\InscriptionsStatusProvider;
use App\Common\Service\RecaptchaVerifier;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Le captcha est vérifié à l'ouverture du brouillon — c'est elle qui ouvre
 * l'accès au dépôt de fichiers, donc la porte à protéger. Injoignable en test
 * fonctionnel : le secret reCAPTCHA y est vide, ce qui désactive la
 * vérification.
 */
class CreateInscriptionDraftUseCaseTest extends TestCase
{
    public function testRejectedCaptchaOpensNoDraft(): void
    {
        $verifier = $this->createStub(RecaptchaVerifier::class);
        $verifier->method('verify')->willReturn(false);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $inscriptions = $this->createStub(InscriptionsStatusProvider::class);
        $inscriptions->method('isFormOpen')->willReturn(true);

        $useCase = new CreateInscriptionDraftUseCase(
            $entityManager,
            $verifier,
            $inscriptions,
            $this->createStub(InscriptionDraftPurger::class),
        );

        $this->expectException(UseCaseException::class);
        $this->expectExceptionMessage('Veuillez valider le captcha.');

        $useCase->run(new CreateInscriptionDraftCommand('token-refuse'));
    }
}
