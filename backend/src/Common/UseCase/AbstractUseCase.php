<?php

namespace App\Common\UseCase;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @template TCommand of CommandInterface|null
 */
abstract class AbstractUseCase
{
    private string $environment = 'prod';
    private ?LoggerInterface $logger = null;

    /**
     * Autowired by the container. Defaults to 'prod' so use cases built
     * outside the container (unit tests) mask internals unless set explicitly.
     */
    #[Required]
    public function setEnvironment(#[Autowire('%kernel.environment%')] string $environment): void
    {
        $this->environment = $environment;
    }

    #[Required]
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

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
            // Seul endroit où cette exception est visible : `execute()` l'avale,
            // donc le listener d'exception du kernel — qui journalise
            // d'habitude — ne la voit jamais. Sans cette ligne une 500 ne
            // laisse aucune trace (ni stderr, ni table `log`) et le client ne
            // reçoit qu'« Unknown Error ».
            $this->logger?->error('Use case failure', [
                'useCase' => static::class,
                'command' => $command !== null ? $command::class : null,
                'exception' => $e,
            ]);

            $isDev = 'dev' === $this->environment;

            return new JsonResponse(
                [
                    'message' => $isDev ? $e->getMessage() : 'Unknown Error',
                    'error' => $isDev ? $this->errorDetails($e) : null,
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function errorDetails(\Throwable $e): array
    {
        return [
            'class' => \get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
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
