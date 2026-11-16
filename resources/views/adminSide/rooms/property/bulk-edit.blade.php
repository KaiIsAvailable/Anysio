@php
    $hierarchy = $gridConfig['hierarchy'] ?? false;
    $groups = [
        ['title' => 'Property', 'fields' => ['name' => 'Property Name', 'ownerId' => 'Owner', 'propertyType' => 'Type']],
        ['title' => 'Address', 'fields' => ['address' => 'Address', 'postcode' => 'Postcode', 'city' => 'City', 'state' => 'State']],
    ];
    $requiredColumns = array_fill_keys(['name', 'propertyType', 'address', 'postcode', 'city', 'state', 'parentKey', 'unitNo', 'roomNo', 'roomType'], 'Required for changed records.');
    $fieldLengths = ['name' => 255, 'address' => 255, 'postcode' => 5, 'city' => 100, 'state' => 100, 'electricityNo' => 12, 'waterNo' => 16, 'indahWaterNo' => 50];
    $propertyOwners = $ownerOptions;
    $propertyOwners[''] = 'No owner';
    $unitOwners = $ownerOptions;
    $unitOwners[''] = 'Use property owner';
    $selectColumns = [
        'state' => ['options' => \App\WorldCountries::worldCountries(), 'placeholder' => 'Select a State'],
        'ownerId' => ['options' => $propertyOwners, 'placeholder' => 'No owner'],
        'propertyType' => ['options' => \App\Models\Property::propertyTypes(), 'placeholder' => 'Choose type'],
    ];
    $sheets = [
        'property' => ['label' => 'Properties', 'groups' => $groups, 'selectColumns' => $selectColumns],
        'unit' => ['label' => 'Units', 'groups' => [
            ['title' => 'Unit', 'fields' => ['parentKey' => 'Property', 'unitNo' => 'Unit No.', 'ownerId' => 'Owner', 'unitType' => 'Type']],
            ['title' => 'Details', 'fields' => ['block' => 'Block', 'floor' => 'Floor', 'sqft' => 'Sqft']],
            ['title' => 'Utilities', 'fields' => ['electricityNo' => 'Electricity Account', 'waterNo' => 'Water Account', 'indahWaterNo' => 'Indah Water Account']],
        ], 'selectColumns' => ['parentKey' => ['parentEntity' => 'property', 'placeholder' => 'Choose property'], 'ownerId' => ['options' => $unitOwners, 'placeholder' => 'Use property owner']]],
        'room' => ['label' => 'Rooms', 'groups' => [
            ['title' => 'Room', 'fields' => ['parentKey' => 'Property / Unit', 'roomNo' => 'Room No.', 'roomType' => 'Room Type']],
        ], 'selectColumns' => ['parentKey' => ['parentEntity' => 'unit', 'placeholder' => 'Choose unit']]],
    ];
    if ($hierarchy) $sheets = array_filter($sheets, fn ($sheet, $entity) => $gridConfig['permissions'][$entity]['view'], ARRAY_FILTER_USE_BOTH);
    $editorFactory = 'propertyBulkEditor';
    $permissionModule = 'property';
    $routePrefix = 'admin.properties';
    $modalPrefix = 'property-bulk';
    $recordTitle = 'Properties';
    $recordSingular = 'Property';
    $recordPlural = 'records';
    $showContacts = false;
@endphp
<x-app-layout>
    @include('components.bulk.editor')
</x-app-layout>
