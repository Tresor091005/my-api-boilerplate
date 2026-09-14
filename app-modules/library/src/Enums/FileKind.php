<?php

declare(strict_types=1);

namespace Lahatre\Library\Enums;

enum FileKind: string
{
    case Image = 'image';
    case Document = 'document';
    case Video = 'video';
    case Audio = 'audio';
    case Archive = 'archive';
    case Other = 'other';

    public static function fromMimeType(string $mimeType): self
    {
        if (str_starts_with($mimeType, 'image/')) {
            return self::Image;
        }

        if (str_starts_with($mimeType, 'video/')) {
            return self::Video;
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return self::Audio;
        }

        if (in_array($mimeType, self::archiveMimeTypes(), true)) {
            return self::Archive;
        }

        if (str_starts_with($mimeType, 'text/') || in_array($mimeType, self::documentMimeTypes(), true)) {
            return self::Document;
        }

        return self::Other;
    }

    /** @return list<string> */
    public static function documentMimeTypes(): array
    {
        return [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
    }

    /** @return list<string> */
    public static function archiveMimeTypes(): array
    {
        return [
            'application/zip',
            'application/x-7z-compressed',
            'application/x-rar-compressed',
            'application/gzip',
            'application/x-tar',
        ];
    }
}
