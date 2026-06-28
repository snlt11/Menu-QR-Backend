<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrivateS3Service
{
    public function uploadPrivateFile(
        UploadedFile $file,
        string $directory,
        ?string $tenantSlug = null,
    ): string {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = Str::ulid()->toBase32().'-'.Str::lower(Str::random(6)).'.'.$ext;

        $path = $tenantSlug
            ? "{$directory}/{$tenantSlug}/{$filename}"
            : "{$directory}/{$filename}";

        Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()), [
            'ContentType' => $file->getMimeType() ?: 'image/jpeg',
        ]);

        return $path;
    }

    public function temporaryUrl(?string $path, ?int $minutes = null): ?string
    {
        if (! $path || $this->isFullUrl($path) || $this->isRelativePublicPath($path)) {
            return $this->resolveImageUrl($path, $minutes);
        }

        try {
            return Storage::disk('s3')->temporaryUrl(
                $path,
                now()->addMinutes($minutes ?? config('menuqr.signed_url_expiry_minutes', 15)),
            );
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function resolveImageUrl(?string $value, ?int $minutes = null): ?string
    {
        if (! $value) {
            return null;
        }

        if ($this->isFullUrl($value)) {
            return $value;
        }

        if ($this->isRelativePublicPath($value)) {
            return rtrim(config('app.url'), '/').$value;
        }

        if ($this->isS3ObjectPath($value)) {
            return $this->temporaryUrl($value, $minutes);
        }

        return null;
    }

    public function deleteIfOwned(?string $path, array $allowedPrefixes = []): void
    {
        if (! $path) {
            return;
        }

        if ($this->isFullUrl($path)) {
            return;
        }

        if ($this->isRelativePublicPath($path)) {
            return;
        }

        if (empty($allowedPrefixes)) {
            return;
        }

        $owned = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $owned = true;
                break;
            }
        }

        if (! $owned) {
            return;
        }

        try {
            if (Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function isFullUrl(?string $value): bool
    {
        if (! $value) {
            return false;
        }

        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }

    public function isRelativePublicPath(?string $value): bool
    {
        if (! $value) {
            return false;
        }

        return str_starts_with($value, '/photos/') || str_starts_with($value, '/storage/');
    }

    public function isS3ObjectPath(?string $value): bool
    {
        if (! $value) {
            return false;
        }

        if ($this->isFullUrl($value) || $this->isRelativePublicPath($value)) {
            return false;
        }

        return ! str_starts_with($value, '/');
    }
}
