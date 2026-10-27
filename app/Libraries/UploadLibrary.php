<?php

namespace App\Libraries;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UploadLibrary
{
    /**
     * Allowed MIME types mapped to safe extensions.
     *
     * @var array<string, string>
     */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    /**
     * Store an uploaded file and return its stored path.
     *
     * @param  string  $directory  e.g. 'materials/pdf'
     * @param  string  $disk  'local' (private — the default; documents like
     *                        driver licenses must never be publicly reachable)
     *                        or 'public' (web-accessible, e.g. marketing images)
     * @return string stored path relative to the disk root
     */
    public static function store(UploadedFile $file, string $directory = 'materials', string $disk = 'local'): string
    {
        $mime = $file->getMimeType() ?: $file->getClientMimeType();
        $extension = self::MIME_EXTENSIONS[$mime] ?? null;

        if ($extension === null) {
            throw new InvalidArgumentException('Unsupported upload type.');
        }

        $filename = Str::uuid().'.'.$extension;

        return $file->storeAs($directory, $filename, $disk);
    }

    /**
     * Delete a file from the given disk.
     */
    public static function delete(string $path, string $disk = 'local'): bool
    {
        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->delete($path);
        }

        return false;
    }

    /**
     * Public URL for a file stored on the 'public' disk.
     */
    public static function url(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Return a guessed type string for display purposes.
     */
    public static function guessType(UploadedFile $file): string
    {
        $mime = $file->getMimeType();

        return match (true) {
            str_contains($mime, 'pdf') => 'pdf',
            str_contains($mime, 'video') => 'video',
            str_contains($mime, 'image') => 'image',
            default => 'document',
        };
    }

    /**
     * Classify external resources for display (link, video hosting, etc.).
     */
    public static function guessTypeFromUrl(string $url): string
    {
        $lower = strtolower($url);

        return match (true) {
            str_contains($lower, 'youtube.com'),
            str_contains($lower, 'youtu.be'),
            str_contains($lower, 'vimeo.com') => 'video',
            default => 'link',
        };
    }
}
