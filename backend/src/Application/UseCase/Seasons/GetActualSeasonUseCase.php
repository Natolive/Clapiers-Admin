<?php

namespace App\Application\UseCase\Seasons;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Season;
use App\Repository\SeasonRepository;

/**
 * @extends AbstractUseCase<null>
 */
class GetActualSeasonUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly SeasonRepository $seasonRepository
    ) {
    }

    protected function run(?CommandInterface $command = null): array
    {
        $seasons = $this->seasonRepository->findAll();

        /** @var Season $season */
        foreach ($seasons as $season) {
            if ($season->isActual()) {
                return $season->toArray();
            }
        }

        throw new UseCaseException('No actual season found');
    }
}
