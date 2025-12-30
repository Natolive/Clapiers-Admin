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
    abstract public function run(?CommandInterface $command = null): mixed;

    /**
     * @param TCommand $command
     * @return Response
     */
    public function execute(?CommandInterface $command = null): Response
    {
        try {
            $result = $this->run($command);

            // Convert entities to arrays
            $data = $this->serializeResult($result);

            return new JsonResponse($data);
        } catch (UseCaseException $e) {
            return new JsonResponse(
                ['message' => $e->getMessage() ?? 'Use Case Error'],
                $e->getCode() ?? Response::HTTP_BAD_REQUEST
            );
        } catch (\Throwable $e) {
            $isDev = ($_ENV['APP_ENV'] ?? 'prod') === 'dev';

            return new JsonResponse(
                [
                    'message' => $isDev ? $e->getMessage() : 'Unknown Error',
                    'error' => $isDev ? [
                        'class' => \get_class($e),
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ] : null,
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function serializeResult(mixed $result): mixed
    {
        // If result has toArray method, use it
        if (is_object($result) && method_exists($result, 'toArray')) {
            return $result->toArray();
        }

        // If result is an array of entities, map each to toArray
        if (is_array($result)) {
            return array_map(function ($item) {
                if (is_object($item) && method_exists($item, 'toArray')) {
                    return $item->toArray();
                }
                return $item;
            }, $result);
        }

        // Otherwise return as is
        return $result;
    }
}
