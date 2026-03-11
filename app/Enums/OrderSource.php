<?php

namespace App\Enums;

enum OrderSource: string
{
    case COUNTER = 'counter';
    case ONLINE_STORE = 'online_store';
    case FACEBOOK = 'facebook';
    case INSTAGRAM = 'instagram';
    case WHATSAPP = 'whatsapp';
    case PHONE = 'phone';
    case OTHER = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            ['label' => 'Counter', 'value' => self::COUNTER->value],
            ['label' => 'Online Store', 'value' => self::ONLINE_STORE->value],
            ['label' => 'Facebook', 'value' => self::FACEBOOK->value],
            ['label' => 'Instagram', 'value' => self::INSTAGRAM->value],
            ['label' => 'WhatsApp', 'value' => self::WHATSAPP->value],
            ['label' => 'Phone', 'value' => self::PHONE->value],
            ['label' => 'Other', 'value' => self::OTHER->value],
        ];
    }
}
