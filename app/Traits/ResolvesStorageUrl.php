<?php

namespace App\Traits;

trait ResolvesStorageUrl
{
    /**
     * Resolve external URLs or local public disk paths into an accessible API storage URL.
     */
    protected function resolveMediaUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (
            str_starts_with($path, 'http://') ||
            str_starts_with($path, 'https://') ||
            str_starts_with($path, 'data:')
        ) {
            return $path;
        }

        $cleanPath = ltrim(preg_replace('#^/?(api/)?storage/#', '', $path), '/');

        return url('/api/storage/' . $cleanPath);
    }
}

