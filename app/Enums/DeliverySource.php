<?php

namespace App\Enums;

enum DeliverySource: string
{
    case STANDALONE = 'standalone';
    case ORDER = 'order';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            ['label' => 'Standalone', 'value' => self::STANDALONE->value],
            ['label' => 'Order', 'value' => self::ORDER->value],
        ];
    }
}
