<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\FeeType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class AccountDefaultsService
{
    public function initialize(User $user): void
    {
        if (! in_array($user->role, [User::ROLE_AGENT_ADMIN, User::ROLE_OWNER_ADMIN], true)) {
            throw new InvalidArgumentException('Account defaults are only available for management accounts.');
        }

        DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            foreach ([
                'invoice' => ['Default Invoice Template', 'Default invoice template for lease billing.'],
                'receipt' => ['Standard Receipt Template', 'Default receipt template for payments.'],
                'refund' => ['Standard Refund Template', 'Default refund statement template.'],
            ] as $category => [$title, $details]) {
                // Preserve edited or intentionally deactivated templates on repeated setup.
                if (DocumentTemplate::where('user_id', $user->id)->where('category', $category)->exists()) {
                    continue;
                }

                DocumentTemplate::create([
                    'user_id' => $user->id,
                    'created_by' => $user->id,
                    'category' => $category,
                    'title' => $title,
                    'details' => $details,
                    'html_template' => File::get(resource_path("document-templates/defaults/{$category}.html")),
                    'status' => 'active',
                    'version' => '1.0.0',
                    'is_system_default' => false,
                ]);
            }

            $this->ensureSystemFeeTypes();
        });
    }

    public function ensureSystemFeeTypes(): void
    {
        DB::transaction(function () {
            // All accounts already use this shared catalogue. Serialize setup across registrations.
            $admin = User::where('role', User::ROLE_ADMIN)->orderBy('id')->lockForUpdate()->firstOrFail();

            foreach (config('default_fee_types') as $feeType) {
                FeeType::firstOrCreate(
                    array_merge($feeType, ['is_system' => true]),
                    ['user_id' => $admin->id, 'is_active' => true]
                );
            }
        });
    }
}
