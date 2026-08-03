<?php

namespace App\Services;

use App\Models\DocumentSequence;
use App\Models\User;

class DocumentSequenceService
{
    public function generateInvoiceNumber(User $user): string
    {
        $sequence = DocumentSequence::firstOrCreate(
            [
                'user_id' => $user->id,
                'category' => 'invoice',
            ],
            [
                'sequence' => [
                    'prefix' => 'INV',
                    'next_number' => 1,
                    'padding' => 5,
                ],
            ]
        );

        $config = $sequence->sequence;

        $invoiceNo = $config['prefix']
            . str_pad(
                $config['next_number'],
                $config['padding'],
                '0',
                STR_PAD_LEFT
            );

        $config['next_number']++;

        $sequence->sequence = $config;
        $sequence->save();

        return $invoiceNo;
    }
}