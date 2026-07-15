<?php

namespace App\Common\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * Stockage physique des fichiers de la médiathèque, sous
 * %upload_directory%/member-media. Centralise nommage UUID, déplacement et
 * suppression (même logique que les uploads licence / certificat).
 *
 * @phpstan-type FileMeta array{storedName: string, originalName: string, mimeType: ?string, size: ?int}
 */
class MemberMediaStorage
{
    private const SUBDIR = '/member-media';

    public function __construct(
        #[Autowire('%upload_directory%')]
        private readonly string $uploadDirectory,
    ) {
    }

    public function directory(): string
    {
        return $this->uploadDirectory.self::SUBDIR;
    }

    public function path(string $storedName): string
    {
        return $this->directory().'/'.$storedName;
    }

    /**
     * Déplace l'upload dans le répertoire médiathèque.
     *
     * @return FileMeta
     */
    public function store(UploadedFile $file): array
    {
        $extension = $file->guessExtension() ?? $file->getClientOriginalExtension();
        $storedName = Uuid::v4()->toRfc4122().($extension !== '' ? '.'.$extension : '');

        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        $file->move($this->directory(), $storedName);

        return [
            'storedName' => $storedName,
            'originalName' => $originalName,
            'mimeType' => $mimeType,
            'size' => $size !== false ? $size : null,
        ];
    }

    public function delete(?string $storedName): void
    {
        if ($storedName === null) {
            return;
        }

        $path = $this->path($storedName);
        if (is_file($path)) {
            unlink($path);
        }
    }
}
