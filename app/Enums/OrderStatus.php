<?php

namespace App\Enums;

enum OrderStatus: string
{
    case CREATED = 'created';
    case CONFIRMED = 'confirmed';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case RETURNED = 'returned';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            ['label' => 'Created', 'value' => self::CREATED->value],
            ['label' => 'Confirmed', 'value' => self::CONFIRMED->value],
            ['label' => 'Completed', 'value' => self::COMPLETED->value],
            ['label' => 'Cancelled', 'value' => self::CANCELLED->value],
            ['label' => 'Returned', 'value' => self::RETURNED->value],
        ];
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::CREATED => in_array($to, [self::CONFIRMED, self::CANCELLED], true),
            self::CONFIRMED => in_array($to, [self::COMPLETED], true),
            self::COMPLETED => in_array($to, [self::RETURNED], true),
            self::CANCELLED, self::RETURNED => false,
        };
    }
}
