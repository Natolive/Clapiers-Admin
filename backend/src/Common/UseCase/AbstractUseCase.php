<?php

namespace App\Common\UseCase;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @template TCommand of CommandInterface|null
 */
abstract class AbstractUseCase
{
    /**
     * @param TCommand $command
     * @return mixed
     */
    abstract protected function run(?CommandInterface $command = null): mixed;

    /**
     * @param TCommand $command
     * @return Response
     */
    public function execute(?CommandInterface $command = null): Response
    {
        try {
            $result = $this->run($command);

            return new JsonResponse($result);
        } catch (UseCaseException $e) {
            return new JsonResponse(
                ['message' => $e->getMessage() ?? 'Use Case Error'],
                $e->getCode() ?? Response::HTTP_BAD_REQUEST
            );
        } catch (\Throwable $e) {
            return new JsonResponse(
                ['message' => 'Unknown Error'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
