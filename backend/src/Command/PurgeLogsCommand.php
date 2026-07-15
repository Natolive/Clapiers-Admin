<?php

namespace App\Command;

use App\Repository\LogRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rétention des logs applicatifs : supprime les lignes plus anciennes que la
 * fenêtre de rétention (14 jours par défaut). À planifier quotidiennement
 * (cron) : `php bin/console app:logs:purge`.
 */
#[AsCommand(
    name: 'app:logs:purge',
    description: 'Purge les logs applicatifs au-delà de la rétention (14 jours par défaut)',
)]
class PurgeLogsCommand extends Command
{
    private const DEFAULT_RETENTION_DAYS = 14;

    public function __construct(
        private readonly LogRepository $logRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'days',
            null,
            InputOption::VALUE_REQUIRED,
            'Nombre de jours de rétention',
            self::DEFAULT_RETENTION_DAYS,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int) $input->getOption('days');
        if ($days < 1) {
            $io->error('Le nombre de jours de rétention doit être un entier positif.');

            return Command::INVALID;
        }

        $threshold = (new \DateTimeImmutable('now'))->modify(sprintf('-%d days', $days));
        $deleted = $this->logRepository->deleteOlderThan($threshold);

        $io->success(sprintf('%d log(s) supprimé(s) (rétention : %d jours).', $deleted, $days));

        return Command::SUCCESS;
    }
}
