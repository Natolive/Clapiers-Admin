<?php

namespace App\Application\UseCase\SalleClosure\CreateSalleClosure;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\SalleClosure;
use App\Repository\SalleClosureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractUseCase<CreateSalleClosureCommand>
 */
class CreateSalleClosureUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly EntityManagerInterface  $entityManager,
        private readonly SalleClosureRepository  $salleClosureRepository,
    ) {
    }

    public function run(?CommandInterface $command = null): SalleClosure
    {
        if (!$command instanceof CreateSalleClosureCommand) {
            throw new UseCaseException('Invalid command');
        }

        if ($command->endDate < $command->startDate) {
            throw new UseCaseException(
                'La date de fin doit être postérieure ou égale à la date de début.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($this->salleClosureRepository->hasOverlap($command->startDate, $command->endDate)) {
            throw new UseCaseException(
                'Une fermeture existe déjà sur cette période.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $closure = new SalleClosure();
        $closure->setStartDate($command->startDate);
        $closure->setEndDate($command->endDate);
        $closure->setReason($command->reason);

        $this->entityManager->persist($closure);
        $this->entityManager->flush();

        return $closure;
    }
}
