<?php

namespace App\Application\UseCase\Season;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\SeasonRepository;

/**
 * @extends AbstractUseCase<null>
 */
class GetAllSeasonsUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly SeasonRepository $seasonRepository
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        return $this->seasonRepository->findAll();
    }
}
