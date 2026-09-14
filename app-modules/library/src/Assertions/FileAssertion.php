<?php

declare(strict_types=1);

namespace Lahatre\Library\Assertions;

use Illuminate\Http\UploadedFile;
use Lahatre\Library\Exceptions\LibraryException;

final readonly class FileAssertion
{
    /**
     * @param  list<UploadedFile>  $files
     *
     * @throws LibraryException
     */
    public function assertUploadAllowed(array $files): void
    {
        $maximumFiles = (int) config('library.upload.max_files');
        $maximumFileSize = (int) config('library.upload.max_file_size');
        $maximumBatchSize = (int) config('library.upload.max_batch_size');
        $allowedMimeTypes = array_values(array_filter(
            (array) config('library.upload.allowed_mimes', []),
            is_string(...),
        ));

        if (count($files) > $maximumFiles) {
            throw LibraryException::uploadFileCountExceeded($maximumFiles);
        }

        $batchSize = 0;

        foreach ($files as $file) {
            $size = (int) ($file->getSize() ?: 0);
            $mimeType = (string) ($file->getMimeType() ?: 'application/octet-stream');

            if ($size > $maximumFileSize) {
                throw LibraryException::uploadFileTooLarge($file->getClientOriginalName(), $maximumFileSize);
            }

            if (!in_array($mimeType, $allowedMimeTypes, true)) {
                throw LibraryException::mimeTypeNotAllowed($file->getClientOriginalName(), $mimeType);
            }

            $batchSize += $size;
        }

        if ($batchSize > $maximumBatchSize) {
            throw LibraryException::uploadBatchTooLarge($maximumBatchSize);
        }
    }

    /** @throws LibraryException */
    public function assertWithinOrganizationQuota(int $used, int $incoming, int $quota): void
    {
        if ($used + $incoming > $quota) {
            throw LibraryException::organizationQuotaExceeded($used, $incoming, $quota);
        }
    }
}
