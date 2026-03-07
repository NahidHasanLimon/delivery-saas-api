<?php

namespace App\Enums;

enum OrderType: string
{
    case DELIVERY = 'delivery';
    case COUNTER = 'counter';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            ['label' => 'Delivery', 'value' => self::DELIVERY->value],
            ['label' => 'Counter', 'value' => self::COUNTER->value],
        ];
    }
}
