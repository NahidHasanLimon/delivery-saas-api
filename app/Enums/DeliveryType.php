<?php

namespace App\Enums;

enum DeliveryType: string
{
    case ORDER = 'order';
    case RETURN = 'return';
    case PICKUP = 'pickup';
    case EXCHANGE = 'exchange';
    case DOCUMENT = 'document';
    case PACKAGE = 'package';
    case FOOD = 'food';
    case MEDICINE = 'medicine';
    case GROCERY = 'grocery';
    case OTHER = 'other';

    /**
     * Get all delivery types as label-value pairs
     */
    public static function options(): array
    {
        return [
            ['label' => 'Order Delivery', 'value' => self::ORDER->value],
            ['label' => 'Return', 'value' => self::RETURN->value],
            ['label' => 'Pickup', 'value' => self::PICKUP->value],
            ['label' => 'Exchange', 'value' => self::EXCHANGE->value],
            ['label' => 'Document Delivery', 'value' => self::DOCUMENT->value],
            ['label' => 'Package Delivery', 'value' => self::PACKAGE->value],
            ['label' => 'Food Delivery', 'value' => self::FOOD->value],
            ['label' => 'Medicine Delivery', 'value' => self::MEDICINE->value],
            ['label' => 'Grocery Delivery', 'value' => self::GROCERY->value],
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
