<?php

namespace App\Enums;

enum UserStatus: string
{
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case NEED_VERIFICATION = 'need_verification';

    /**
     * @return array<string, string>
     */
    public static function getValues(): array
    {
        return array_column(self::cases(), 'value', 'name');
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
        return $this === self::NEED_VERIFICATION;
    }
}
