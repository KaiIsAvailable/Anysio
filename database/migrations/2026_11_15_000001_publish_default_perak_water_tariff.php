<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $tariff = DB::table('utility_tariffs')->whereNull('management_user_id')
                ->where('kind', 'official')->where('reference_key', 'water_perak_domestic')->lockForUpdate()->first();
            if (!$tariff) throw new RuntimeException('Import utility tariff references before publishing the default water tariff.');

            // Preserve any reference already verified by the platform administrator.
            if (DB::table('utility_tariff_versions')->where('utility_tariff_id', $tariff->id)->whereNotNull('verified_at')->exists()) return;

            $now = now();
            DB::table('utility_tariff_versions')->insert([
                'id' => (string) Str::ulid(), 'utility_tariff_id' => $tariff->id,
                'version' => DB::table('utility_tariff_versions')->where('utility_tariff_id', $tariff->id)->max('version') + 1,
                'effective_from' => '2024-05-01',
                'definition' => json_encode([
                    'calculation' => 'blocks', 'minimum_policy' => 'monthly', 'minimum_charge' => '7.500000',
                    'tiers' => [['limit' => '20.000', 'rate' => '0.750000'], ['limit' => '35.000', 'rate' => '1.080000'], ['limit' => null, 'rate' => '1.350000']],
                    'capacity_rate' => '0', 'network_rate' => '0', 'fixed_charge' => '0', 'fixed_waiver_up_to' => null,
                    'billing_note' => 'Published LAP domestic individual-meter base rates. Domestic rebates and subsidies are excluded.',
                    '_metadata' => ['name' => 'Perak — Lembaga Air Perak — Domestic individual meter',
                        'provider' => 'Lembaga Air Perak', 'state' => 'Perak', 'customer_category' => 'Domestic individual meter'],
                ], JSON_UNESCAPED_UNICODE),
                'source_url' => 'https://www.petra.gov.my/uploads/content-downloads/file_20241014224414.pdf',
                'verified_at' => '2026-11-15 00:00:00',
                'reason' => 'Bundled base schedule checked against P.U. (A) 34/2024 on 15 November 2026. Perak implementation from 1 May 2024 reported by RTM: https://berita.rtm.gov.my/nasional/senarai-berita-nasional/senarai-artikel/kadar-baharu-tarif-air-perak-naik-5-sen/ . Excludes rebates and subsidies. No saved charges or account overrides changed.',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Published tariff versions are retained for billing history. Use a forward migration.');
    }
};
