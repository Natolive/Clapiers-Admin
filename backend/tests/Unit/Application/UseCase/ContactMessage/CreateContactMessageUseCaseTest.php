<?php

namespace App\Tests\Unit\Application\UseCase\ContactMessage;

use App\Application\UseCase\ContactMessage\CreateContactMessage\CreateContactMessageCommand;
use App\Application\UseCase\ContactMessage\CreateContactMessage\CreateContactMessageUseCase;
use App\Common\Exception\UseCaseException;
use App\Common\Service\RecaptchaVerifier;
use App\Entity\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

class CreateContactMessageUseCaseTest extends TestCase
{
    public function testRejectedCaptchaBlocksTheMessage(): void
    {
        $verifier = $this->createStub(RecaptchaVerifier::class);
        $verifier->method('verify')->willReturn(false);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $useCase = $this->makeUseCase($verifier, $entityManager, $this->createStub(MailerInterface::class));

        $this->expectException(UseCaseException::class);
        $this->expectExceptionMessage('Veuillez valider le captcha.');

        $useCase->run($this->command());
    }

    public function testMailerFailureDoesNotPreventPersistence(): void
    {
        $verifier = $this->createStub(RecaptchaVerifier::class);
        $verifier->method('verify')->willReturn(true);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('SMTP down'));

        $useCase = $this->makeUseCase($verifier, $entityManager, $mailer);

        $message = $useCase->run($this->command());

        // Sending is best-effort: the message survives a mailer outage
        $this->assertInstanceOf(ContactMessage::class, $message);
        $this->assertSame('jean@test.fr', $message->getEmail());
    }

    private function makeUseCase(
        RecaptchaVerifier $verifier,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
    ): CreateContactMessageUseCase {
        return new CreateContactMessageUseCase(
            $entityManager,
            $mailer,
            new NullLogger(),
            $verifier,
            'club@test.fr',
            'noreply@test.fr',
        );
    }

    private function command(): CreateContactMessageCommand
    {
        return new CreateContactMessageCommand(
            firstName: 'Jean',
            lastName: 'Dupont',
            email: 'jean@test.fr',
            subject: 'Inscription',
            message: 'Bonjour',
            recaptchaToken: 'token',
        );
    }
}
