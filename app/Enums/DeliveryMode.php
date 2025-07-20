<?php

namespace App\Enums;

enum DeliveryMode: string
{
    case BIKE = 'bike';
    case MOTORCYCLE = 'motorcycle';
    case CAR = 'car';
    case VAN = 'van';
    case TRUCK = 'truck';
    case WALK = 'walk';
    case BICYCLE = 'bicycle';
    case SCOOTER = 'scooter';
    case PUBLIC_TRANSPORT = 'public_transport';
    case OTHER = 'other';

    /**
     * Get all delivery modes as label-value pairs
     */
    public static function options(): array
    {
        return [
            ['label' => 'Bike', 'value' => self::BIKE->value],
            ['label' => 'Motorcycle', 'value' => self::MOTORCYCLE->value],
            ['label' => 'Car', 'value' => self::CAR->value],
            ['label' => 'Van', 'value' => self::VAN->value],
            ['label' => 'Truck', 'value' => self::TRUCK->value],
            ['label' => 'Walking', 'value' => self::WALK->value],
            ['label' => 'Bicycle', 'value' => self::BICYCLE->value],
            ['label' => 'Scooter', 'value' => self::SCOOTER->value],
            ['label' => 'Public Transport', 'value' => self::PUBLIC_TRANSPORT->value],
            ['label' => 'Other', 'value' => self::OTHER->value],
        ];
    }

    /**
     * Get all enum values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
