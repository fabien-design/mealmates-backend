<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case RESERVED = 'reserved';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    
    /**
     * @return array<string, string>
     */
    public static function getValues(): array
    {
        return array_column(self::cases(), 'value', 'name');
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isReserved(): bool
    {
        return $this === self::RESERVED;
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    public function isRefunded(): bool
    {
        return $this === self::REFUNDED;
    }
}
