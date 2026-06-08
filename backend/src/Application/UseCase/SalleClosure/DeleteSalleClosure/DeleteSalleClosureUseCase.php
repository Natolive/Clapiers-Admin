<?php

namespace App\Application\UseCase\SalleClosure\DeleteSalleClosure;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\SalleClosureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractUseCase<DeleteSalleClosureCommand>
 */
class DeleteSalleClosureUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly SalleClosureRepository  $salleClosureRepository,
        private readonly EntityManagerInterface  $entityManager,
    ) {
    }

    public function run(?CommandInterface $command = null): null
    {
        if (!$command instanceof DeleteSalleClosureCommand) {
            throw new UseCaseException('Invalid command');
        }

        $closure = $this->salleClosureRepository->find($command->id);
        if (!$closure) {
            throw new UseCaseException('Closure not found', Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($closure);
        $this->entityManager->flush();

        return null;
    }
}
