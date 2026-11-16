<?php

namespace Database\Seeders;

use App\Services\AccountDefaultsService;
use Illuminate\Database\Seeder;

class FeeTypeSeeder extends Seeder
{
    public function run(): void
    {
        app(AccountDefaultsService::class)->ensureSystemFeeTypes();
    }
}
