<?php

namespace App\Application\UseCase\SalleClosure\CreateSalleClosure;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\SalleClosure;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractUseCase<CreateSalleClosureCommand>
 */
class CreateSalleClosureUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function run(?CommandInterface $command = null): SalleClosure
    {
        if (!$command instanceof CreateSalleClosureCommand) {
            throw new UseCaseException('Invalid command');
        }

        $start = new DateTimeImmutable($command->startDate);
        $end   = new DateTimeImmutable($command->endDate);

        if ($end < $start) {
            throw new UseCaseException(
                'La date de fin doit être postérieure ou égale à la date de début.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $closure = new SalleClosure();
        $closure->setStartDate($start);
        $closure->setEndDate($end);
        $closure->setReason($command->reason);

        $this->entityManager->persist($closure);
        $this->entityManager->flush();

        return $closure;
    }
}
