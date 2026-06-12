<?php

namespace App\Application\UseCase\Game\ImportGames;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Enum\AppUserRole;
use App\Entity\Enum\GameVenue;
use App\Entity\Game;
use App\Entity\Team;
use App\Repository\TeamRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Importe une liste de matchs depuis un fichier CSV.
 *
 * Format CSV attendu (séparateur virgule, encodage UTF-8) :
 *
 *   team,opponent,date,venue,meetingTime,location
 *
 * Colonnes :
 *   - team        : integer  — identifiant de l'équipe en base de données
 *   - opponent    : string   — nom de l'équipe adverse (max 255 caractères)
 *   - date        : string   — date du match au format JJ/MM/AAAA  (ex: 23/03/2026)
 *   - venue       : string   — réception : "HOME" ou "AWAY" (insensible à la casse)
 *   - meetingTime : string   — heure de rendez-vous au format HH:MM (max 10 car.) — optionnel
 *   - location    : string   — nom et ville du gymnase (max 255 car.)              — optionnel
 *
 * Comportement transactionnel :
 *   Toutes les lignes sont validées PUIS insérées dans une unique transaction.
 *   En cas d'erreur (validation ou BDD), la transaction est annulée (rollback)
 *   et aucun match n'est persisté.
 *
 * @extends AbstractUseCase<ImportGamesCommand>
 */
class ImportGamesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly TeamRepository         $teamRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{imported: int}
     * @throws UseCaseException
     */
    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof ImportGamesCommand) {
            throw new UseCaseException('Invalid command');
        }

        $isSuperAdmin = in_array(AppUserRole::ROLE_SUPER_ADMIN, $command->user->getRoles(), true);
        if (!$isSuperAdmin) {
            throw new UseCaseException('Accès refusé', Response::HTTP_FORBIDDEN);
        }

        $rows = $this->parseCsv($command->csvContent);

        if (empty($rows)) {
            throw new UseCaseException(
                'Le fichier CSV est vide ou ne contient aucune ligne de données.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Validate and build all Game entities before touching the database.
        // This way, a row validation error never leaves a partial import.
        $games = [];
        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2; // ligne 1 = en-têtes, index 0 = ligne 2
            $games[]    = $this->buildGame($row, $lineNumber);
        }

        // Insert everything in a single transaction — rollback on any error.
        $this->entityManager->beginTransaction();
        try {
            foreach ($games as $game) {
                $this->entityManager->persist($game);
            }
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Throwable $e) {
            $this->entityManager->rollback();
            throw new UseCaseException(
                'Erreur lors de l\'insertion en base de données : ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return ['imported' => count($games)];
    }

    // ── CSV parsing ───────────────────────────────────────────────────────────

    /**
     * Lit le contenu CSV et retourne un tableau de lignes associatives.
     *
     * @return array<int, array<string, string>>
     * @throws UseCaseException si les en-têtes sont manquants ou invalides
     */
    private function parseCsv(string $content): array
    {
        // Supprimer le BOM UTF-8 éventuel
        $content = ltrim($content, "\xEF\xBB\xBF");

        $lines = array_values(
            array_filter(
                explode("\n", str_replace("\r\n", "\n", trim($content))),
                static fn(string $l): bool => trim($l) !== ''
            )
        );

        if (count($lines) < 2) {
            return [];
        }

        $headerLine = array_shift($lines);
        $headers    = array_map('trim', str_getcsv($headerLine, escape: '\\'));

        $requiredHeaders = ['team', 'opponent', 'date', 'venue', 'meetingTime', 'location'];
        $missing         = array_diff($requiredHeaders, $headers);
        if (!empty($missing)) {
            throw new UseCaseException(
                sprintf(
                    'En-tête(s) manquant(s) : "%s". En-têtes attendus : %s',
                    implode('", "', $missing),
                    implode(', ', $requiredHeaders)
                ),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $rows = [];
        foreach ($lines as $line) {
            $values = str_getcsv(trim($line), escape: '\\');
            if (count($values) !== count($headers)) {
                continue; // ignorer les lignes malformées (ex: ligne vide finale)
            }
            $rows[] = array_combine($headers, $values);
        }

        return $rows;
    }

    // ── Row → Game entity ─────────────────────────────────────────────────────

    /**
     * Construit et valide un objet Game à partir d'une ligne CSV.
     *
     * @param array<string, string> $row
     * @throws UseCaseException si une valeur est invalide
     */
    private function buildGame(array $row, int $lineNumber): Game
    {
        $team        = $this->resolveTeam($row, $lineNumber);
        $opponent    = $this->resolveOpponent($row, $lineNumber);
        $date        = $this->resolveDate($row, $lineNumber);
        $venue       = $this->resolveVenue($row, $lineNumber);
        $meetingTime = $this->resolveMeetingTime($row, $lineNumber);
        $location    = $this->resolveLocation($row, $lineNumber);

        $game = new Game();
        $game->setOpponent($opponent);
        $game->setDate($date);
        $game->setMeetingTime($meetingTime);
        $game->setVenue($venue);
        $game->setLocation($location);
        $game->setTeam($team);

        return $game;
    }

    // ── Field resolvers ───────────────────────────────────────────────────────

    /**
     * team : integer — identifiant de l'équipe en base de données
     * @throws UseCaseException
     */
    private function resolveTeam(array $row, int $lineNumber): Team
    {
        $raw = trim($row['team'] ?? '');

        if ($raw === '' || !ctype_digit($raw)) {
            throw new UseCaseException(
                sprintf('Ligne %d : "team" doit être un entier positif (valeur reçue : "%s").', $lineNumber, $raw),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $team = $this->teamRepository->find((int) $raw);
        if (!$team) {
            throw new UseCaseException(
                sprintf('Ligne %d : aucune équipe trouvée avec l\'identifiant %s.', $lineNumber, $raw),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $team;
    }

    /**
     * opponent : string — nom de l'équipe adverse (max 255 caractères)
     * @throws UseCaseException
     */
    private function resolveOpponent(array $row, int $lineNumber): string
    {
        $opponent = trim($row['opponent'] ?? '');

        if ($opponent === '') {
            throw new UseCaseException(
                sprintf('Ligne %d : "opponent" est requis et ne peut pas être vide.', $lineNumber),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (strlen($opponent) > 255) {
            throw new UseCaseException(
                sprintf('Ligne %d : "opponent" dépasse 255 caractères.', $lineNumber),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $opponent;
    }

    /**
     * date : string — format JJ/MM/AAAA (ex: 23/03/2026)
     * @throws UseCaseException
     */
    private function resolveDate(array $row, int $lineNumber): DateTimeImmutable
    {
        $raw  = trim($row['date'] ?? '');
        $date = DateTimeImmutable::createFromFormat('d/m/Y', $raw);

        if ($date === false) {
            throw new UseCaseException(
                sprintf(
                    'Ligne %d : "date" doit être au format JJ/MM/AAAA (valeur reçue : "%s").',
                    $lineNumber,
                    $raw
                ),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $date->setTime(0, 0);
    }

    /**
     * venue : string — "HOME" ou "AWAY" (insensible à la casse)
     * @throws UseCaseException
     */
    private function resolveVenue(array $row, int $lineNumber): GameVenue
    {
        $raw   = trim($row['venue'] ?? '');
        $venue = GameVenue::tryFrom(strtolower($raw));

        if ($venue === null) {
            throw new UseCaseException(
                sprintf(
                    'Ligne %d : "venue" doit être "HOME" ou "AWAY" (valeur reçue : "%s").',
                    $lineNumber,
                    $raw
                ),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $venue;
    }

    /**
     * meetingTime : string — heure de rendez-vous au format HH:MM (optionnel, max 10 caractères)
     * @throws UseCaseException
     */
    private function resolveMeetingTime(array $row, int $lineNumber): ?string
    {
        $meetingTime = trim($row['meetingTime'] ?? '') ?: null;

        if ($meetingTime !== null && strlen($meetingTime) > 10) {
            throw new UseCaseException(
                sprintf(
                    'Ligne %d : "meetingTime" dépasse 10 caractères (valeur reçue : "%s").',
                    $lineNumber,
                    $meetingTime
                ),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $meetingTime;
    }

    /**
     * location : string — nom et ville du gymnase (optionnel, max 255 caractères)
     * @throws UseCaseException
     */
    private function resolveLocation(array $row, int $lineNumber): ?string
    {
        $location = trim($row['location'] ?? '') ?: null;

        if ($location !== null && strlen($location) > 255) {
            throw new UseCaseException(
                sprintf('Ligne %d : "location" dépasse 255 caractères.', $lineNumber),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $location;
    }
}
