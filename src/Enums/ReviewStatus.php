<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case NEEDS_VERIFICATION = 'needs_verification';
    
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

    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }
    
    public function needsVerification(): bool
    {
        return $this === self::NEEDS_VERIFICATION;
    }
}
