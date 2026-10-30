<?php

namespace App\Tests\Unit\Application\UseCase\User;

use App\Application\UseCase\User\RequestPasswordReset\RequestPasswordResetCommand;
use App\Application\UseCase\User\RequestPasswordReset\RequestPasswordResetUseCase;
use App\Common\Exception\UseCaseException;
use App\Common\Service\AccountMailer;
use App\Common\Service\RecaptchaVerifier;
use App\Entity\AppUser;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

class RequestPasswordResetUseCaseTest extends TestCase
{
    private function useCase(bool $captchaOk, MailerInterface $mailer, ?AppUser $user = null): RequestPasswordResetUseCase
    {
        $captcha = $this->createStub(RecaptchaVerifier::class);
        $captcha->method('verify')->willReturn($captchaOk);
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneBy')->willReturn($user);

        return new RequestPasswordResetUseCase(
            $users,
            $this->createStub(EntityManagerInterface::class),
            $captcha,
            new AccountMailer($mailer, 'club@test.fr', 'http://localhost:3000'),
            new NullLogger(),
        );
    }

    public function testRefusedCaptchaIsRejected(): void
    {
        $this->expectException(UseCaseException::class);
        $this->useCase(false, $this->createStub(MailerInterface::class))
            ->run(new RequestPasswordResetCommand('coach@test.fr'));
    }

    /** Un échec d'envoi ne doit rien révéler : même réponse que d'habitude. */
    public function testMailerOutageStillAnswersTheGenericMessage(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('SMTP down'));

        $result = $this->useCase(true, $mailer, (new AppUser())->setEmail('coach@test.fr'))
            ->run(new RequestPasswordResetCommand('coach@test.fr'));

        $this->assertSame(['message' => RequestPasswordResetUseCase::MESSAGE], $result);
    }
}
