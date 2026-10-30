<?php

namespace App\Tests\Unit\Application\UseCase\User;

use App\Application\UseCase\User\InviteCoach\InviteCoachCommand;
use App\Application\UseCase\User\InviteCoach\InviteCoachUseCase;
use App\Common\Exception\UseCaseException;
use App\Common\Service\AccountMailer;
use App\Entity\Member;
use App\Entity\Team;
use App\Repository\MemberRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class InviteCoachUseCaseTest extends TestCase
{
    /** Sans le mail l'invité ne peut pas activer son compte : l'admin doit le savoir. */
    public function testMailerOutageIsReportedToTheAdmin(): void
    {
        $member = (new Member())->setFirstName('Léa')->setLastName('Martin')->setEmail('lea@test.fr');

        $members = $this->createStub(MemberRepository::class);
        $members->method('find')->willReturn($member);
        $teams = $this->createStub(TeamRepository::class);
        $teams->method('find')->willReturn((new Team())->setName('Seniors'));
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hash');
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('SMTP down'));

        $useCase = new InviteCoachUseCase(
            $members,
            $teams,
            $this->createStub(UserRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $hasher,
            new AccountMailer($mailer, 'club@test.fr', 'http://localhost:3000'),
        );

        $this->expectException(UseCaseException::class);
        $this->expectExceptionCode(502);
        $useCase->run(new InviteCoachCommand(1, [1]));
    }
}
