<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'Cash';
    case CHEQUE = 'Cheque';
    case BANK_TRANSFER = 'Bank Transfer';
    case CREDIT_CARD = 'Credit Card';
    case DEBIT_CARD = 'Debit Card';
    case DIGITAL_WALLET = 'e-Wallet / Digital Wallet';
    case DIGITAL_BANK = 'Digital Bank';
    case OTHERS = 'Others';

    public function code(): string
    {
        return match ($this) {
            self::CASH => '01',
            self::CHEQUE => '02',
            self::BANK_TRANSFER => '03',
            self::CREDIT_CARD => '04',
            self::DEBIT_CARD => '05',
            self::DIGITAL_WALLET => '06',
            self::DIGITAL_BANK => '07',
            self::OTHERS => '08',
        };
    }

    public function label(): string
    {
        return $this->value . ' (' . $this->code() . ')';
    }

    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $method) {
            $options[$method->value] = $method->label();
        }

        return $options;
    }
}
