<?php

namespace App\Tests\Unit\Controller;

use App\Application\UseCase\Team\DownloadMyTeamMemberLicense\DownloadMyTeamMemberLicenseUseCase;
use App\Controller\TeamController;
use App\Entity\AppUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Covers the Throwable fallback of the manual error mapping around the binary
 * license download: unreachable over HTTP (the use case checks file_exists
 * just before), only a race deleting the file in between can trigger it.
 */
class TeamControllerTest extends TestCase
{
    public function testUnexpectedFailureDuringLicenseDownloadStaysJson(): void
    {
        $useCase = $this->createStub(DownloadMyTeamMemberLicenseUseCase::class);
        $useCase->method('run')->willThrowException(new \RuntimeException('fichier disparu'));

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new AppUser(), 'main'));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);

        $controller = new TeamController();
        $controller->setContainer($container);

        $response = $controller->downloadMyTeamMemberLicense(1, $useCase);

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('Unknown Error', $body['message']);
    }
}
