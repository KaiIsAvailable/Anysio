<?php

namespace App\Services;

use App\Models\DocumentSequence;
use App\Models\User;

class DocumentSequenceService
{
    /**
     * Generate a unique document number based on category (e.g., invoice, receipt).
     */
    public function generateNumber(User $user, string $category = 'invoice', string $defaultPrefix = 'INV'): string
    {
        $sequence = DocumentSequence::firstOrCreate(
            [
                'user_id' => $user->id,
                'category' => $category,
            ],
            [
                'sequence' => [
                    'prefix' => $defaultPrefix,
                    'next_number' => 1,
                    'padding' => 5,
                ],
            ]
        );

        $config = $sequence->sequence;

        $documentNo = $config['prefix']
            . str_pad(
                $config['next_number'],
                $config['padding'],
                '0',
                STR_PAD_LEFT
            );

        $config['next_number']++;

        $sequence->sequence = $config;
        $sequence->save();

        return $documentNo;
    }

    public function generateInvoiceNumber(User $user): string
    {
        return $this->generateNumber($user, 'invoice', 'INV');
    }

    public function generateReceiptNumber(User $user): string
    {
        return $this->generateNumber($user, 'receipt', 'REC');
    }
}