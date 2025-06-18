<?php

namespace App\Enums;

enum ImageExtension: string
{
    case JPG = 'jpg';
    case JPEG = 'jpeg';
    case WEBP = 'webp';
    case PNG = 'png';
    
    /**
     * @return array<string, string>
     */
    public static function getValues(): array
    {
        return array_column(self::cases(), 'value', 'name');
    }
}
