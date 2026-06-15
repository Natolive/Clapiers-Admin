<?php

namespace App\Tests\Unit\Controller;

use App\Application\UseCase\Game\ImportGames\ImportGamesUseCase;
use App\Controller\GameController;
use App\Entity\AppUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Covers the unreadable-tmp-file guard in import(): once the MIME constraint
 * has validated the upload, the tmp file always exists over HTTP, so this
 * branch is only reachable by deleting the file between validation and read.
 */
class GameControllerTest extends TestCase
{
    public function testImportRejectsAnUnreadableUploadedFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "team,opponent\n");
        $file = new UploadedFile($path, 'games.csv', 'text/csv', null, true);
        unlink($path);

        $useCase = $this->createMock(ImportGamesUseCase::class);
        $useCase->expects($this->never())->method('execute');

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new AppUser(), 'main'));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);

        $controller = new GameController();
        $controller->setContainer($container);

        set_error_handler(static fn (): bool => true, E_WARNING);
        try {
            $response = $controller->import($file, $useCase);
        } finally {
            restore_error_handler();
        }

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('Impossible de lire le fichier uploadé.', $body['message']);
    }
}
