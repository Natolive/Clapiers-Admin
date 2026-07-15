<?php

namespace App\Tests\Unit\Application\UseCase\License;

use App\Application\UseCase\License\CreateCheckout\CreateCheckoutCommand;
use App\Application\UseCase\License\CreateCheckout\CreateCheckoutUseCase;
use App\Entity\Enum\LicenseStatus;
use App\Entity\License;
use App\Entity\Member;
use App\Repository\LicenseRepository;
use App\Tests\Support\Fake\FakeHelloAssoClient;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CreateCheckoutUseCaseTest extends TestCase
{
    public function testCheckoutUrlsFallBackToPreprodWhenFrontendUrlMissing(): void
    {
        $member = (new Member())
            ->setFirstName('Marie')
            ->setLastName('Curie')
            ->setEmail('marie@test.fr');
        $license = (new License())
            ->setMember($member)
            ->setSeason('2026-2027')
            ->setStatus(LicenseStatus::VALIDEE)
            ->setAmount(12000);

        $repository = $this->createStub(LicenseRepository::class);
        $repository->method('findOneByAccessToken')->willReturn($license);

        $helloAsso = new FakeHelloAssoClient();

        // APP_FRONTEND_URL non injecté → l'env processor `default::` fournit null.
        $useCase = new CreateCheckoutUseCase(
            $repository,
            $this->createStub(EntityManagerInterface::class),
            $helloAsso,
            null,
        );

        $useCase->run(new CreateCheckoutCommand($license->getAccessToken() ?? 'tok'));

        $body = $helloAsso->createdCheckoutBodies[0];
        $base = 'https://preprod.clapiersvb.fr/licence/'.$license->getAccessToken();
        $this->assertSame($base.'?status=success', $body['returnUrl']);
        $this->assertSame($base.'?status=back', $body['backUrl']);
    }
}
