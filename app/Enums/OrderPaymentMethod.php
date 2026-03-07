<?php

namespace App\Enums;

enum OrderPaymentMethod: string
{
    case CASH = 'cash';
    case ONLINE = 'online';
    case COD = 'cod';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            ['label' => 'Cash', 'value' => self::CASH->value],
            ['label' => 'Online', 'value' => self::ONLINE->value],
            ['label' => 'Cash on Delivery', 'value' => self::COD->value],
        ];
    }
}

