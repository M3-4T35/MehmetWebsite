<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileUploader
{
    public function __construct(private readonly string $targetDirectory)
    {
    }

    /**
     * Moves the uploaded file into the target directory and returns the generated filename.
     */
    public function upload(UploadedFile $file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
        $fileName = ($safeFilename ?: 'fichier').'-'.uniqid().'.'.$file->guessExtension();

        if (!is_dir($this->targetDirectory) && !@mkdir($this->targetDirectory, 0775, true) && !is_dir($this->targetDirectory)) {
            throw new \RuntimeException(sprintf('Le dossier cible "%s" n\'a pas pu être créé.', $this->targetDirectory));
        }

        if (!is_writable($this->targetDirectory)) {
            throw new \RuntimeException(sprintf('Le dossier "%s" n\'est pas accessible en écriture pour l\'utilisateur du serveur web (www-data). Corrigez les permissions ou les ACL.', $this->targetDirectory));
        }

        try {
            $file->move($this->targetDirectory, $fileName);
        } catch (FileException $e) {
            throw new \RuntimeException(sprintf('Impossible de déplacer le fichier vers "%s".', $this->targetDirectory), 0, $e);
        }

        return $fileName;
    }

    /**
     * Deletes a file from the target directory. Refuses anything resolving outside it.
     */
    public function remove(?string $filename): void
    {
        if (!$filename) {
            return;
        }

        $base = realpath($this->targetDirectory);
        $path = realpath($this->targetDirectory.'/'.$filename);

        if (!$base || !$path || !str_starts_with($path, $base.DIRECTORY_SEPARATOR) || !is_file($path)) {
            return;
        }

        @unlink($path);
    }
}
