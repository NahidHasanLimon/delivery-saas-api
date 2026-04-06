<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case CREATED = 'created';
    case ASSIGNED = 'assigned';
    case ACCEPTED = 'accepted';
    case IN_PROGRESS = 'in_progress';
    case DELIVERED = 'delivered';
    case RETURNED = 'returned';
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
            ['label' => 'Created', 'value' => self::CREATED->value],
            ['label' => 'Assigned', 'value' => self::ASSIGNED->value],
            ['label' => 'Accepted', 'value' => self::ACCEPTED->value],
            ['label' => 'In Progress', 'value' => self::IN_PROGRESS->value],
            ['label' => 'Delivered', 'value' => self::DELIVERED->value],
            ['label' => 'Returned', 'value' => self::RETURNED->value],
            ['label' => 'Cancelled', 'value' => self::CANCELLED->value],
        ];
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::CREATED => in_array($to, [self::ASSIGNED, self::ACCEPTED, self::IN_PROGRESS, self::DELIVERED, self::CANCELLED]),
            self::ASSIGNED => in_array($to, [self::ACCEPTED, self::IN_PROGRESS, self::DELIVERED, self::CANCELLED]),
            self::ACCEPTED => in_array($to, [self::IN_PROGRESS, self::DELIVERED, self::CANCELLED]),
            self::IN_PROGRESS => in_array($to, [self::DELIVERED, self::CANCELLED]),
            self::DELIVERED => in_array($to, [self::RETURNED]),
            self::RETURNED, self::CANCELLED => false,
        };
    }
}
