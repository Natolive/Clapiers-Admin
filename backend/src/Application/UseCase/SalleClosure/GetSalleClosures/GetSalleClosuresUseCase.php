<?php

namespace App\Application\UseCase\SalleClosure\GetSalleClosures;

use App\Common\Command\CommandInterface;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\SalleClosureRepository;

class GetSalleClosuresUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly SalleClosureRepository $salleClosureRepository,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        return $this->salleClosureRepository->findAllOrderedByDate();
    }
}
