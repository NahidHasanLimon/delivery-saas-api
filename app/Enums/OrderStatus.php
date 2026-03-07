<?php

namespace App\Enums;

enum OrderStatus: string
{
    case NEW = 'new';
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
            ['label' => 'New', 'value' => self::NEW->value],
            ['label' => 'Confirmed', 'value' => self::CONFIRMED->value],
            ['label' => 'Completed', 'value' => self::COMPLETED->value],
            ['label' => 'Cancelled', 'value' => self::CANCELLED->value],
            ['label' => 'Returned', 'value' => self::RETURNED->value],
        ];
    }
}

