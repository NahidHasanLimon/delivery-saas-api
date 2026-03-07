<?php

namespace App\Enums;

enum OrderDeliveryStatus: string
{
    case PENDING = 'pending';
    case ASSIGNED = 'assigned';
    case IN_PROGRESS = 'in_progress';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            ['label' => 'Pending', 'value' => self::PENDING->value],
            ['label' => 'Assigned', 'value' => self::ASSIGNED->value],
            ['label' => 'In Progress', 'value' => self::IN_PROGRESS->value],
            ['label' => 'Delivered', 'value' => self::DELIVERED->value],
            ['label' => 'Failed', 'value' => self::FAILED->value],
        ];
    }
}

