<?php

namespace App\Tests\Unit\Common\UseCase;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class AbstractUseCaseTest extends TestCase
{
    public function testResultWithToArrayIsSerialized(): void
    {
        $useCase = $this->useCaseReturning(new class {
            public function toArray(): array
            {
                return ['id' => 1, 'name' => 'Équipe'];
            }
        });

        $response = $useCase->execute();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(['id' => 1, 'name' => 'Équipe'], json_decode($response->getContent(), true));
    }

    public function testArrayOfEntitiesIsMappedItemByItem(): void
    {
        $entity = new class {
            public function toArray(): array
            {
                return ['id' => 1];
            }
        };

        $useCase = $this->useCaseReturning([$entity, 'raw-value', 42]);

        $response = $useCase->execute();

        $this->assertSame([['id' => 1], 'raw-value', 42], json_decode($response->getContent(), true));
    }

    public function testScalarResultIsReturnedAsIs(): void
    {
        $useCase = $this->useCaseReturning('done');

        $response = $useCase->execute();

        $this->assertSame('"done"', $response->getContent());
    }

    public function testUseCaseExceptionIsMappedToItsStatusCode(): void
    {
        $useCase = $this->useCaseThrowing(new UseCaseException('Introuvable', Response::HTTP_NOT_FOUND));

        $response = $useCase->execute();

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame(['message' => 'Introuvable'], json_decode($response->getContent(), true));
    }

    public function testUseCaseExceptionDefaultsToBadRequest(): void
    {
        $useCase = $this->useCaseThrowing(new UseCaseException('Erreur métier'));

        $response = $useCase->execute();

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testUnexpectedExceptionIsMaskedAs500OutsideDev(): void
    {
        $useCase = $this->useCaseThrowing(new \RuntimeException('secret interne'));

        $response = $useCase->execute();

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        // APP_ENV=test here, so internals must not leak
        $this->assertSame('Unknown Error', $body['message']);
        $this->assertNull($body['error']);
    }

    private function useCaseReturning(mixed $result): AbstractUseCase
    {
        return new class($result) extends AbstractUseCase {
            public function __construct(private readonly mixed $result)
            {
            }

            public function run(?CommandInterface $command = null): mixed
            {
                return $this->result;
            }
        };
    }

    private function useCaseThrowing(\Throwable $exception): AbstractUseCase
    {
        return new class($exception) extends AbstractUseCase {
            public function __construct(private readonly \Throwable $exception)
            {
            }

            public function run(?CommandInterface $command = null): mixed
            {
                throw $this->exception;
            }
        };
    }
}
