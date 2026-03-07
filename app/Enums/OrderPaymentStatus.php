<?php

namespace App\Enums;

enum OrderPaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PARTIAL = 'partial';
    case PAID = 'paid';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            ['label' => 'Unpaid', 'value' => self::UNPAID->value],
            ['label' => 'Partial', 'value' => self::PARTIAL->value],
            ['label' => 'Paid', 'value' => self::PAID->value],
        ];
    }
}

