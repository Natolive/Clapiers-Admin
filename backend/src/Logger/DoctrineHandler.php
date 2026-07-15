<?php

namespace App\Logger;

use Doctrine\DBAL\Connection;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Handler Monolog qui persiste chaque enregistrement dans la table `log`,
 * consultable depuis la page d'administration (SUPER_ADMIN).
 *
 * L'écriture passe par une insertion DBAL brute (jamais par l'ORM) : elle reste
 * fiable même lorsqu'une exception a laissé l'EntityManager fermé. Toute erreur
 * d'insertion est avalée — journaliser ne doit jamais casser la requête.
 *
 * Branché sur le canal par défaut via config/packages/monolog.yaml.
 */
class DoctrineHandler extends AbstractProcessingHandler
{
    private const DEFAULT_RETENTION_DAYS = 14;

    private bool $writing = false;
    private bool $pruned = false;

    public function __construct(
        private readonly Connection $connection,
        private readonly int $retentionDays = self::DEFAULT_RETENTION_DAYS,
        int|string|Level $level = Level::Warning,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        // Garde-fou anti-récursion : une requête déclenchée par l'insertion ne
        // doit pas relancer une écriture de log.
        if ($this->writing) {
            return;
        }

        $this->writing = true;
        try {
            $this->connection->insert('log', [
                'level' => strtolower($record->level->getName()),
                'channel' => $record->channel,
                'message' => $record->message,
                'context' => $this->encodeContext($record),
                'created_at' => $record->datetime->format('Y-m-d H:i:s'),
            ]);

            // Rétention appliquée au fil de l'eau, une seule fois par process :
            // pas de cron à planifier, et un seul DELETE par requête qui journalise.
            $this->pruneOldLogs();
        } catch (\Throwable) {
            // Journaliser ne doit jamais faire échouer la requête.
        } finally {
            $this->writing = false;
        }
    }

    private function pruneOldLogs(): void
    {
        if ($this->pruned) {
            return;
        }
        // Marqué avant exécution : un échec de purge ne doit pas être retenté en
        // boucle sur les logs suivants du même process.
        $this->pruned = true;

        $threshold = (new \DateTimeImmutable('now'))
            ->modify(sprintf('-%d days', max(1, $this->retentionDays)))
            ->format('Y-m-d H:i:s');

        $this->connection->executeStatement(
            'DELETE FROM log WHERE created_at < :threshold',
            ['threshold' => $threshold],
        );
    }

    private function encodeContext(LogRecord $record): ?string
    {
        $context = $record->context;
        if ($context === []) {
            return null;
        }

        $normalized = [];
        foreach ($context as $key => $value) {
            $normalized[$key] = $this->normalize($value);
        }

        $json = json_encode(
            $normalized,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );

        return $json !== false ? $json : null;
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof \Throwable) {
            return [
                'class' => $value::class,
                'message' => $value->getMessage(),
                'code' => $value->getCode(),
                'at' => $value->getFile().':'.$value->getLine(),
            ];
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->normalize($item), $value);
        }

        if (is_object($value)) {
            return $value instanceof \Stringable ? (string) $value : '[object '.$value::class.']';
        }

        return $value;
    }
}
