<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case PENDING = 'pending';
    case ASSIGNED = 'assigned';
    case IN_PROGRESS = 'in_progress';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all delivery statuses as label-value pairs
     */
    public static function options(): array
    {
        return [
            ['label' => 'Pending', 'value' => self::PENDING->value],
            ['label' => 'Assigned', 'value' => self::ASSIGNED->value],
            ['label' => 'In Progress', 'value' => self::IN_PROGRESS->value],
            ['label' => 'Delivered', 'value' => self::DELIVERED->value],
            ['label' => 'Cancelled', 'value' => self::CANCELLED->value],
        ];
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::PENDING => in_array($to, [self::ASSIGNED, self::IN_PROGRESS, self::DELIVERED, self::CANCELLED]),
            self::ASSIGNED => in_array($to, [self::IN_PROGRESS, self::DELIVERED, self::CANCELLED]),
            self::IN_PROGRESS => in_array($to, [self::DELIVERED, self::CANCELLED]),
            self::DELIVERED, self::CANCELLED => false,
        };
    }
}
