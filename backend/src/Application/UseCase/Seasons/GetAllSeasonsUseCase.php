<?php

namespace App\Application\UseCase\Seasons;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\SeasonsRepository;

/**
 * @extends AbstractUseCase<null>
 */
class GetAllSeasonsUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly SeasonsRepository $seasonsRepository
    ) {
    }

    protected function run(?CommandInterface $command = null): array
    {
        $seasons = $this->seasonsRepository->findAll();
        return array_map(fn($season) => $season->toArray(), $seasons);
    }
}
