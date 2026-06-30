<?php

namespace App\Application\UseCase\License\UploadMedicalCertificate;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\License;
use App\Repository\LicenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * @extends AbstractUseCase<UploadMedicalCertificateCommand>
 */
class UploadMedicalCertificateUseCase extends AbstractUseCase
{
    private const SUBDIR = '/medical-certificates';

    public function __construct(
        private readonly LicenseRepository $licenseRepository,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%upload_directory%')]
        private readonly string $uploadDirectory,
    ) {
    }

    public function run(?CommandInterface $command = null): License
    {
        if (!$command instanceof UploadMedicalCertificateCommand) {
            throw new UseCaseException('Invalid command');
        }

        $license = $this->licenseRepository->findOneByAccessToken($command->token);

        if (!$license) {
            throw new UseCaseException('Licence introuvable', Response::HTTP_NOT_FOUND);
        }

        // Remplace l'éventuel ancien fichier
        if ($license->getMedicalCertificateFileName()) {
            $oldPath = $this->uploadDirectory.self::SUBDIR.'/'.$license->getMedicalCertificateFileName();
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $extension = $command->file->guessExtension() ?? $command->file->getClientOriginalExtension();
        $fileName = Uuid::v4()->toRfc4122().'.'.$extension;

        $command->file->move($this->uploadDirectory.self::SUBDIR, $fileName);

        $license->setMedicalCertificateFileName($fileName);
        $this->entityManager->flush();

        return $license;
    }
}
