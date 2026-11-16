@php
    $groups = [
        ['title' => 'Owner', 'fields' => ['name' => 'Full Name', 'email' => 'Login Email', 'ic' => 'IC Number', 'phone' => 'Owner Phone', 'gender' => 'Gender']],
        ['title' => 'Owner Address', 'fields' => ['address' => 'Owner Address', 'postcode' => 'Postcode', 'city' => 'City', 'state' => 'State']],
        ['title' => 'Company (Optional)', 'fields' => ['companyName' => 'Company Name', 'ssmOld' => 'SSM Old Number', 'ssmNew' => 'SSM New Number', 'companyPhone' => 'Company Phone', 'companyEmail' => 'Company Email', 'companyAddress' => 'Company Address', 'companyLogo' => 'Company Logo', 'ssmCertificate' => 'SSM Certificate']],
        ['title' => 'Bank Account (Optional)', 'fields' => ['bankName' => 'Bank Name', 'bankCode' => 'Bank Code', 'accountHolder' => 'Account Holder', 'accountNumber' => 'Account Number']],
    ];
    $requiredColumns = ['name' => 'Required for every owner.', 'ic' => 'Required for every owner.', 'phone' => 'Required for every owner.', 'gender' => 'Required for every owner.'];
    $selectColumns = ['state' => ['options' => ['' => 'Not specified'] + \App\WorldCountries::worldCountries(), 'placeholder' => 'Select a State']];
    $fieldLengths = ['name' => 255, 'email' => 255, 'ic' => 12, 'phone' => 20, 'address' => 1000, 'postcode' => 10, 'city' => 100, 'state' => 100,
        'companyName' => 255, 'ssmOld' => 255, 'ssmNew' => 50, 'companyPhone' => 20, 'companyEmail' => 255, 'companyAddress' => 500,
        'bankName' => 255, 'bankCode' => 100, 'accountHolder' => 255, 'accountNumber' => 50];
    $editorFactory = 'ownerBulkEditor';
    $permissionModule = 'owner';
    $routePrefix = 'admin.owners';
    $modalPrefix = 'owner-bulk';
    $recordTitle = 'Owners';
    $recordSingular = 'Owner';
    $recordPlural = 'owners';
    $showContacts = false;
@endphp
<x-app-layout>
    @include('components.bulk.editor')
</x-app-layout>
