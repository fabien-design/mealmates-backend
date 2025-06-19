<?php

namespace App\Enums;

enum OfferStatus: string
{
    case ACTIVE = 'active';
    case SOLD = 'sold';
    case EXPIRED = 'expired';
    case DELETED = 'deleted';
    case ALL = 'all';

    /**
     * @return array<string, string>
     */
    public static function getValues(): array
    {
        return array_column(self::cases(), 'value', 'name');
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isSold(): bool
    {
        return $this === self::SOLD;
    }

    public function isExpired(): bool
    {
        return $this === self::EXPIRED;
    }

    public function isDeleted(): bool
    {
        return $this === self::DELETED;
    }

    public function isAll(): bool
    {
        return $this === self::ALL;
    }
}
