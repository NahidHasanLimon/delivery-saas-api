<?php

namespace App\Enums;

enum OrderDeliveryMedium: string
{
    case MANUAL = 'manual';
    case OWN = 'own';
    case THIRD_PARTY = 'third_party';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            ['label' => 'Manual', 'value' => self::MANUAL->value],
            ['label' => 'Own Delivery', 'value' => self::OWN->value],
            ['label' => 'Third Party', 'value' => self::THIRD_PARTY->value],
        ];
    }
}

