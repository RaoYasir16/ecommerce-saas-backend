<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait FileUploadTrait
{
    /**
     * Upload file to local public disk.
     */
    protected function uploadFile(
        UploadedFile $file,
        string $directory
    ): string {
        return $file->store($directory, 'public');
    }

    /**
     * Delete file from local public disk.
     */
    protected function deleteFile(?string $path): bool
    {
        if (!$path) {
            return false;
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }

        return false;
    }

    /**
     * Upload file to Amazon S3.
     */
    protected function uploadS3File(
        UploadedFile $file,
        string $directory
    ): string {
        return $file->store($directory, 's3');
    }

    /**
     * Delete file from Amazon S3.
     */
    protected function deleteS3File(?string $path): bool
    {
        if (!$path) {
            return false;
        }

        if (Storage::disk('s3')->exists($path)) {
            return Storage::disk('s3')->delete($path);
        }

        return false;
    }
}