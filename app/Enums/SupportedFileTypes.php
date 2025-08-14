<?php

namespace App\Enums;

/**
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/MIME_types/Common_types
 */
enum SupportedFileTypes: string
{
    case JPEG = 'image/jpeg';

    case JSON = 'application/json';

    case JSON_LD = 'application/ld+json';

    case TXT = 'text/plain';

    case ZIP = 'application/zip';

    case ZIP_COMPRESSED = 'application/x-zip-compressed';

    case EMPTY = 'application/x-empty';

    public static function getMimeType(string $path): string
    {
        $mimeType = mime_content_type($path);

        if ($mimeType === self::EMPTY->value) {
            $mimeType = self::mapFileExtensionToMimeType($path);
        }

        return $mimeType;
    }

    public static function isValid(string $path): bool
    {
        $mimeType = self::getMimeType($path);

        $values = array_column(self::cases(), 'value');

        return in_array($mimeType, $values, true);
    }

    private static function mapFileExtensionToMimeType(string $path): string
    {
        $mapping = [
            'jpeg' => self::JPEG->value,
            'jpg' => self::JPEG->value,
            'json' => self::JSON->value,
            'jsonld' => self::JSON_LD->value,
            'txt' => self::TXT->value,
            'zip' => self::ZIP->value,
        ];

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $mapping[mb_strtolower($extension)] ?? self::EMPTY->value;
    }
}
