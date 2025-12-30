<?php

namespace App\Application\UseCase\Seasons;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Seasons;
use App\Repository\SeasonsRepository;

/**
 * @extends AbstractUseCase<null>
 */
class GetActualSeasonUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly SeasonsRepository $seasonsRepository
    ) {
    }

    protected function run(?CommandInterface $command = null): array
    {
        $seasons = $this->seasonsRepository->findAll();

        /** @var Seasons $season */
        foreach ($seasons as $season) {
            if ($season->isActual()) {
                return $season->toArray();
            }
        }

        throw new UseCaseException('No actual season found');
    }
}
