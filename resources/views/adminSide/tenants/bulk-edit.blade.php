@php
    $groups = [
        ['title' => 'Tenant', 'fields' => ['name' => 'Full Name', 'email' => 'Login Email', 'type' => 'Type', 'phone' => 'Tenant Phone', 'ic' => 'IC Number', 'passport' => 'Passport', 'nationality' => 'Nationality', 'gender' => 'Gender', 'occupation' => 'Occupation', 'icPhoto' => 'IC Photo']],
        ['title' => 'Company', 'fields' => ['companyName' => 'Company Name', 'ssmOld' => 'SSM Old Number', 'ssmNew' => 'SSM New Number', 'companyPhone' => 'Company Phone', 'companyEmail' => 'Company Email', 'companyAddress' => 'Company Address', 'companyLogo' => 'Company Logo', 'ssmCertificate' => 'SSM Certificate']],
    ];
    if ($canEditBank) {
        $groups[] = ['title' => 'Bank Account', 'fields' => ['bankName' => 'Bank Name', 'bankCode' => 'Bank Code', 'accountHolder' => 'Account Holder', 'accountNumber' => 'Account Number']];
    }
    $requiredColumns = [
        'name' => 'Required for every tenant.',
        'type' => 'Required for every tenant.',
        'phone' => 'Required for every tenant.',
        'ic' => 'Individual tenants: enter either IC or passport, not both.',
        'passport' => 'Individual tenants: enter either IC or passport, not both.',
        'nationality' => 'Required for individual tenants. IC sets this to MALAYSIAN.',
        'gender' => 'Required for individual tenants.',
        'companyName' => 'Required for corporate tenants.',
        'ssmNew' => 'Required for corporate tenants.',
        'companyAddress' => 'Required for corporate tenants.',
    ];
    $fieldLengths = [
        'name' => 255, 'email' => 255, 'phone' => 20, 'ic' => 12, 'passport' => 30,
        'nationality' => 100, 'occupation' => 100, 'companyName' => 255,
        'ssmOld' => 255, 'ssmNew' => 50, 'companyPhone' => 20, 'companyEmail' => 255,
        'companyAddress' => 500, 'bankName' => 255, 'bankCode' => 100,
        'accountHolder' => 255, 'accountNumber' => 50,
    ];
    $records = $tenants;
    $editorFactory = 'tenantBulkEditor';
    $permissionModule = 'tenant';
    $routePrefix = 'admin.tenants';
    $modalPrefix = 'tenant-bulk';
    $recordTitle = 'Tenants';
    $recordSingular = 'Tenant';
    $recordPlural = 'tenants';
    $showContacts = true;
@endphp
<x-app-layout>
    @include('components.bulk.editor')
</x-app-layout>
