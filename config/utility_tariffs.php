<?php

// Legacy configured reference data. All rates are unverified until a Super Admin records documentary verification.
// These are base tariffs, not a claim to include subsidies, tax or monthly adjustments.
$water = [
    'selangor' => ['Selangor / Kuala Lumpur / Putrajaya', 'Air Selangor', [0.65, 1.62, 3.51], 6.50, '2025-09-01', 'https://content.airselangor.com/cmi-prod-blobstorageanon-001-sea/media/netdevbr/appendix-a.pdf', 2.09, 173],
    'johor' => ['Johor', 'Ranhill SAJ', [1.05, 2.35, 3.50], 10.50, '2025-08-01', 'https://www.ranhillsaj.com.my/water-tariff', 2.75, 27.50],
    'kedah' => ['Kedah', 'SADA', [0.81, 1.48, 2.00], 8.10, '2025-08-01', 'https://www.sada.com.my/KadarTarif', 1.83, 18.30],
    'kelantan' => ['Kelantan', 'Air Kelantan', [0.80, 1.55, 2.10], 8.00, '2025-09-10', 'https://airkelantan.com.my/maklumat-pengguna/kadar-tarif/', 2.17, 21.70],
    'melaka' => ['Melaka', 'SAMB', null, null, null, 'https://samb.com.my/en/tarif-air/'],
    'negeri_sembilan' => ['Negeri Sembilan', 'SAINS', null, null, null, 'https://www.sainswater.com.my/'],
    'pahang' => ['Pahang', 'PAIP', null, null, null, 'https://www.paip.com.my/pengguna/tarif-bekalan-air/'],
    'penang' => ['Penang', 'PBAPP', [0.65, 1.30, 2.21], 6.50, '2026-07-01', 'https://pbahb.com.my/pdf/annual-report/PBA-AR2025.pdf', 1.92, 19.20],
    // Legacy configured individual-meter rates, not verified current official prices. Rebate is not included.
    'perak' => ['Perak', 'Lembaga Air Perak', [0.75, 1.08, 1.35], 7.50, '2024-05-01', 'https://www.petra.gov.my/uploads/content-downloads/file_20241014224414.pdf'],
    'perlis' => ['Perlis', 'Syarikat Air Perlis', null, null, null, 'https://airperlis.com.my/'],
    'terengganu' => ['Terengganu', 'SATU', [0.66, 1.69, 2.25], 6.60, '2025-08-01', 'https://www.satuwater.com.my/v4/index.php/bisnes/kadar-tarif', 2.25, 22.50],
    'sabah' => ['Sabah', 'Jabatan Air Sabah', [0.30, 0.60, 1.10, 1.30, 1.80], 3.00, null, 'https://water.sabah.gov.my/content/water-tariff', 1.30, 150],
    'sarawak' => ['Sarawak', 'Sarawak Water', [0.48, 0.72, 0.76], 4.40, null, 'https://sarawakwater.com/rate-charges/water-supply-charges'],
    'labuan' => ['Labuan', 'Jabatan Bekalan Air Labuan', null, null, null, 'https://www.jba.gov.my/'],
];
$profiles = [];
foreach ($water as $state => $entry) {
    [$region, $provider, $rates, $minimum, $date, $source] = $entry;
    $limits = match ($state) {
        'sabah' => [10, 20, 35, 60, null],
        'sarawak' => [15, 50, null],
        default => [20, 35, null],
    };
    foreach (['domestic', 'bulk', 'non_domestic'] as $category) {
        $bulk = $category === 'bulk';
        $known = $category === 'domestic' ? $rates !== null : ($bulk && isset($entry[6]));
        $tiers = $bulk ? [['limit' => null, 'rate' => $entry[6] ?? null]] : array_map(
            fn ($limit, $index) => ['limit' => $limit, 'rate' => $category === 'domestic' ? ($rates[$index] ?? null) : null],
            $limits, array_keys($limits)
        );
        $profiles['water_'.$state.'_'.$category] = [
            'label' => $region.' — '.$provider.' — '.match ($category) {
                'domestic' => 'Domestic individual meter', 'bulk' => 'Domestic bulk meter', default => 'Non-domestic / custom',
            },
            'kind' => 'water', 'unit' => 'm³', 'source' => $source,
            'reference_available' => false,
            'effective_date' => $known ? $date : null,
            'calculation' => $bulk ? 'flat' : 'blocks', 'tiers' => $tiers,
            'minimum_charge' => $category === 'domestic' ? $minimum : ($bulk ? ($entry[7] ?? null) : null),
            'capacity_rate' => 0, 'network_rate' => 0, 'fixed_charge' => 0, 'fixed_waiver_up_to' => null,
        ];
    }
}
$profiles['electricity_tnb_domestic'] = [
    'label' => 'Peninsular Malaysia — TNB — Domestic general', 'kind' => 'electricity', 'unit' => 'kWh',
    'source' => 'https://myenergystats.st.gov.my/documents/d/guest/tariff-tnb-pdf-1',
    'reference_available' => false, 'effective_date' => '2025-07-01',
    'calculation' => 'whole_usage',
    'tiers' => [['limit' => 1500, 'rate' => 0.2703], ['limit' => null, 'rate' => 0.3703]],
    'capacity_rate' => 0.0455, 'network_rate' => 0.1285,
    'minimum_charge' => 0, 'fixed_charge' => 10, 'fixed_waiver_up_to' => 600,
];
foreach (['sabah_labuan' => 'Sabah / Labuan — Sabah Electricity', 'sarawak' => 'Sarawak — Sarawak Energy'] as $id => $label) {
    $profiles['electricity_'.$id] = [
        'label' => $label.' — Custom verified bill rates', 'kind' => 'electricity', 'unit' => 'kWh',
        'source' => $id === 'sarawak' ? 'https://www.sarawakenergy.com/customers/tariffs' : 'https://www.sesb.com.my/tariff',
        'reference_available' => false, 'effective_date' => null,
        'calculation' => 'blocks', 'tiers' => [['limit' => null, 'rate' => null]],
        'capacity_rate' => 0, 'network_rate' => 0, 'minimum_charge' => null,
        'fixed_charge' => 0, 'fixed_waiver_up_to' => null,
    ];
}
return [
    'checked_on' => null,
    // Only controls the settings selector; all profiles remain available in configuration.
    'visible_profiles' => ['water_perak_domestic'],
    'profiles' => $profiles,
];
