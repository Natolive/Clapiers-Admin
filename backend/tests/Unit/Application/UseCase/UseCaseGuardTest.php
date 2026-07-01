<?php

namespace App\Tests\Unit\Application\UseCase;

use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tous les use cases refusent une commande du mauvais type ("Invalid command").
 * Cette garde est injoignable via HTTP (les contrôleurs construisent toujours la
 * bonne commande) : on l'exerce ici en instanciant chaque use case avec des
 * dépendances factices via réflexion.
 */
class UseCaseGuardTest extends TestCase
{
    #[DataProvider('useCaseClassProvider')]
    public function testRunWithoutCommandIsRejected(string $useCaseClass): void
    {
        $useCase = $this->instantiateWithStubs($useCaseClass);

        try {
            $useCase->run(null);
            $this->fail(sprintf('%s::run(null) should throw UseCaseException', $useCaseClass));
        } catch (UseCaseException $e) {
            $this->assertSame('Invalid command', $e->getMessage());
        }
    }

    public static function useCaseClassProvider(): iterable
    {
        $classes = [
            \App\Application\UseCase\ContactMessage\CreateContactMessage\CreateContactMessageUseCase::class,
            \App\Application\UseCase\Game\CreateUpdateGame\CreateUpdateGameUseCase::class,
            \App\Application\UseCase\Game\DeleteGame\DeleteGameUseCase::class,
            \App\Application\UseCase\Game\GetGameHistory\GetGameHistoryUseCase::class,
            \App\Application\UseCase\Game\GetGames\GetGamesUseCase::class,
            \App\Application\UseCase\Game\ImportGames\ImportGamesUseCase::class,
            \App\Application\UseCase\License\ApproveLicense\ApproveLicenseUseCase::class,
            \App\Application\UseCase\License\CreateCheckout\CreateCheckoutUseCase::class,
            \App\Application\UseCase\License\GetLicenseForPayment\GetLicenseForPaymentUseCase::class,
            \App\Application\UseCase\License\GetPaginatedLicenses\GetPaginatedLicensesUseCase::class,
            \App\Application\UseCase\License\HandleHelloAssoWebhook\HandleHelloAssoWebhookUseCase::class,
            \App\Application\UseCase\License\RejectLicense\RejectLicenseUseCase::class,
            \App\Application\UseCase\License\SubmitLicenseRequest\SubmitLicenseRequestUseCase::class,
            \App\Application\UseCase\License\UploadMedicalCertificate\UploadMedicalCertificateUseCase::class,
            \App\Application\UseCase\Member\CreateUpdateMember\CreateUpdateMemberUseCase::class,
            \App\Application\UseCase\Member\DeleteLicense\DeleteLicenseUseCase::class,
            \App\Application\UseCase\Member\DeleteProfilePicture\DeleteProfilePictureUseCase::class,
            \App\Application\UseCase\Member\UploadLicense\UploadLicenseUseCase::class,
            \App\Application\UseCase\Member\UploadProfilePicture\UploadProfilePictureUseCase::class,
            \App\Application\UseCase\SalleClosure\CreateSalleClosure\CreateSalleClosureUseCase::class,
            \App\Application\UseCase\SalleClosure\DeleteSalleClosure\DeleteSalleClosureUseCase::class,
            \App\Application\UseCase\Team\CreateUpdateTeam\CreateUpdateTeamUseCase::class,
            \App\Application\UseCase\Team\DownloadMyTeamMemberLicense\DownloadMyTeamMemberLicenseUseCase::class,
            \App\Application\UseCase\Team\GetMyTeam\GetMyTeamUseCase::class,
            \App\Application\UseCase\User\CreateUpdateUser\CreateUpdateUserUseCase::class,
            \App\Application\UseCase\User\LinkMember\LinkMemberUseCase::class,
            \App\Application\UseCase\User\UnlinkMember\UnlinkMemberUseCase::class,
        ];

        foreach ($classes as $class) {
            yield $class => [$class];
        }
    }

    /** Builds the use case with a stub for every constructor dependency. */
    private function instantiateWithStubs(string $useCaseClass): AbstractUseCase
    {
        $constructor = (new \ReflectionClass($useCaseClass))->getConstructor();
        $args = [];

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();
            \assert($type instanceof \ReflectionNamedType);

            $args[] = match (true) {
                !$type->isBuiltin() => $this->createStub($type->getName()),
                $type->getName() === 'string' => 'test-value',
                default => throw new \LogicException(sprintf(
                    'Unsupported constructor parameter $%s for %s',
                    $parameter->getName(),
                    $useCaseClass,
                )),
            };
        }

        return new $useCaseClass(...$args);
    }
}
