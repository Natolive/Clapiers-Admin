<?php

namespace App\Tests\Unit\Controller;

use App\Application\UseCase\Member\ExportMembers\ExportMembersUseCase;
use App\Common\Exception\UseCaseException;
use App\Controller\MemberController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Response;

/**
 * Couvre le mappage d'erreurs fait à la main autour de l'export : run() renvoie
 * un fichier, donc execute() (qui emballe tout en JSON) est inutilisable, et le
 * contrôleur retraduit les exceptions lui-même. Aucune des deux branches n'est
 * atteignable en HTTP — la validation de la commande refuse en amont tout ce
 * qui ferait échouer le use case — d'où ce test unitaire.
 */
class MemberControllerTest extends TestCase
{
    public function testRefusedExportIsMappedToItsStatusCode(): void
    {
        $useCase = $this->createStub(ExportMembersUseCase::class);
        $useCase->method('run')->willThrowException(
            new UseCaseException('Commande invalide', Response::HTTP_BAD_REQUEST),
        );

        $response = $this->controller()->export(null, $useCase);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame('Commande invalide', $this->message($response));
    }

    public function testUnexpectedFailureDuringExportStaysJson(): void
    {
        $useCase = $this->createStub(ExportMembersUseCase::class);
        $useCase->method('run')->willThrowException(new \RuntimeException('disque plein'));

        $response = $this->controller()->export(null, $useCase);

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        // Le détail ne fuit pas : l'appelant reçoit la même forme JSON que partout.
        $this->assertSame('Unknown Error', $this->message($response));
    }

    private function controller(): MemberController
    {
        $controller = new MemberController();
        $controller->setContainer(new Container());

        return $controller;
    }

    private function message(Response $response): string
    {
        return json_decode((string) $response->getContent(), true)['message'];
    }
}
