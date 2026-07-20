<?php

namespace App\Enums;

enum OrderSource: string
{
    case Website = 'website';
    case Facebook = 'facebook';
    case Messenger = 'messenger';
    case Whatsapp = 'whatsapp';
    case Direct = 'direct';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Facebook => 'Facebook',
            self::Messenger => 'Messenger',
            self::Whatsapp => 'WhatsApp',
            self::Direct => 'Direct',
            self::Other => 'Other',
        };
    }
}
