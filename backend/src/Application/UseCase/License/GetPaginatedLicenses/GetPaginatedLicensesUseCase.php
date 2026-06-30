<?php

namespace App\Application\UseCase\License\GetPaginatedLicenses;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Enum\LicenseStatus;
use App\Entity\License;
use App\Repository\LicenseRepository;

/**
 * @extends AbstractUseCase<GetPaginatedLicensesCommand>
 */
class GetPaginatedLicensesUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly LicenseRepository $licenseRepository,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof GetPaginatedLicensesCommand) {
            throw new UseCaseException('Invalid command');
        }

        $status = $command->status !== null ? LicenseStatus::tryFrom($command->status) : null;

        $licenses = $this->licenseRepository->findPaginated($command->page, $command->limit, $status, $command->search);
        $total = $this->licenseRepository->countByFilters($status, $command->search);

        return [
            'data' => array_map(fn (License $license) => $license->toArray(), $licenses),
            'total' => $total,
        ];
    }
}
