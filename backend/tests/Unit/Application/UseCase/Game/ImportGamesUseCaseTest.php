<?php

namespace App\Tests\Unit\Application\UseCase\Game;

use App\Application\UseCase\Game\ImportGames\ImportGamesCommand;
use App\Application\UseCase\Game\ImportGames\ImportGamesUseCase;
use App\Common\Exception\UseCaseException;
use App\Entity\AppUser;
use App\Entity\Enum\AppUserRole;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Branches injoignables via HTTP (la route est déjà protégée par IsGranted,
 * et le sniffing MIME bloque les fichiers sans ligne de données valide).
 */
class ImportGamesUseCaseTest extends TestCase
{
    public function testCsvWithNoDataRowIsRejectedAsEmpty(): void
    {
        $command = new ImportGamesCommand(
            user: $this->superAdmin(),
            csvContent: "team,opponent,date,venue,meetingTime,location\n",
        );

        try {
            $this->makeUseCase()->run($command);
            $this->fail('Expected UseCaseException');
        } catch (UseCaseException $e) {
            $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getCode());
            $this->assertStringContainsString('vide', $e->getMessage());
        }
    }

    public function testCompletelyEmptyFileIsRejected(): void
    {
        $command = new ImportGamesCommand(user: $this->superAdmin(), csvContent: '');

        $this->expectException(UseCaseException::class);
        $this->expectExceptionMessageMatches('/vide/');

        $this->makeUseCase()->run($command);
    }

    public function testUtf8BomIsStripped(): void
    {
        // BOM before the header must not break header detection: the file is
        // rejected as empty (no data row), not for missing headers
        $command = new ImportGamesCommand(
            user: $this->superAdmin(),
            csvContent: "\xEF\xBB\xBFteam,opponent,date,venue,meetingTime,location\n",
        );

        $this->expectExceptionMessageMatches('/vide/');

        $this->makeUseCase()->run($command);
    }

    public function testLinesWithWrongColumnCountAreSkipped(): void
    {
        // Unreachable over HTTP: an inconsistent line makes the MIME sniffer
        // reject the whole file as text/plain before the use case runs
        $team = new \App\Entity\Team();
        $team->setName('Équipe 7');

        $teamRepository = $this->createMock(TeamRepository::class);
        $teamRepository->expects($this->once())->method('find')->with(7)->willReturn($team);

        $useCase = new ImportGamesUseCase(
            $teamRepository,
            $this->createStub(EntityManagerInterface::class),
        );

        $result = $useCase->run(new ImportGamesCommand(
            user: $this->superAdmin(),
            csvContent: "team,opponent,date,venue,meetingTime,location\n"
                ."7,Perols,23/03/2026,HOME,14:30,Gymnase A\n"
                ."only,three,columns\n",
        ));

        $this->assertSame(['imported' => 1], $result);
    }

    public function testDatabaseFailureRollsBackAndSurfacesA500(): void
    {
        $team = new \App\Entity\Team();
        $team->setName('Équipe 7');

        $teamRepository = $this->createStub(TeamRepository::class);
        $teamRepository->method('find')->willReturn($team);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('beginTransaction');
        $entityManager->method('flush')->willThrowException(new \RuntimeException('connexion perdue'));
        $entityManager->expects($this->once())->method('rollback');
        $entityManager->expects($this->never())->method('commit');

        $useCase = new ImportGamesUseCase($teamRepository, $entityManager);

        try {
            $useCase->run(new ImportGamesCommand(
                user: $this->superAdmin(),
                csvContent: "team,opponent,date,venue,meetingTime,location\n7,Perols,23/03/2026,HOME,,\n",
            ));
            $this->fail('Expected UseCaseException');
        } catch (UseCaseException $e) {
            $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getCode());
            $this->assertStringContainsString('connexion perdue', $e->getMessage());
        }
    }

    public function testNonSuperAdminIsRefused(): void
    {
        $coach = new AppUser();
        $coach->setEmail('coach@test.fr');
        $coach->setRoles([AppUserRole::ROLE_ADMIN]);

        $command = new ImportGamesCommand(user: $coach, csvContent: "whatever\n");

        try {
            $this->makeUseCase()->run($command);
            $this->fail('Expected UseCaseException');
        } catch (UseCaseException $e) {
            $this->assertSame(Response::HTTP_FORBIDDEN, $e->getCode());
        }
    }

    private function makeUseCase(): ImportGamesUseCase
    {
        return new ImportGamesUseCase(
            $this->createStub(TeamRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
    }

    private function superAdmin(): AppUser
    {
        $user = new AppUser();
        $user->setEmail('admin@test.fr');
        $user->setRoles([AppUserRole::ROLE_SUPER_ADMIN]);

        return $user;
    }
}
