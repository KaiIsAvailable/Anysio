<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TenantBulkEditViewTest extends TestCase
{
    public function test_spreadsheet_cells_render_as_inputs_instead_of_component_tags(): void
    {
        $this->actingAs(new User(['name' => 'Manager', 'role' => 'admin']));
        Gate::before(fn () => true);

        // Render the sheet without the application layout's unrelated database queries.
        $source = file_get_contents(resource_path('views/adminSide/tenants/bulk-edit.blade.php'));
        $source = str_replace(['<x-app-layout>', '</x-app-layout>'], '', $source);
        $html = Blade::render($source, [
            'rows' => collect(),
            'tenants' => new LengthAwarePaginator([], 0, 50),
            'canEditBank' => true,
        ]);

        $this->assertStringNotContainsString('<x-form.', $html);
        foreach (['name', 'email', 'phone', 'companyName', 'accountNumber'] as $field) {
            $this->assertMatchesRegularExpression('/<input\b[^>]*x-model="row\.'.preg_quote($field, '/').'"[^>]*>/s', $html);
        }
        $this->assertStringContainsString('row.accountLastFour', $html);
        $this->assertMatchesRegularExpression('/<th[^>]*>\s*Full Name\s*<span class="text-red-600"/s', $html);
        $this->assertMatchesRegularExpression('/<th[^>]*>\s*SSM Old Number\s*<\/th>/s', $html);
        $this->assertMatchesRegularExpression('/<th[^>]*>\s*Occupation\s*<\/th>/s', $html);
        $this->assertStringContainsString('Required for corporate tenants.', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        foreach (['icPhoto', 'companyLogo', 'ssmCertificate'] as $field) {
            $this->assertMatchesRegularExpression('/<input\b(?=[^>]*type="file")(?=[^>]*data-upload-field="'.preg_quote($field, '/').'")[^>]*>/s', $html);
        }
        $this->assertMatchesRegularExpression('/<th[^>]*>\s*IC Photo\s*<\/th>/s', $html);
        $this->assertStringContainsString('Document Preview', $html);
        $this->assertStringContainsString('Saving Tenants', $html);
        $this->assertStringContainsString('aria-label="Tenant saving progress"', $html);
        $this->assertStringContainsString('Go to error on the left', $html);
        $this->assertStringContainsString('Go to error on the right', $html);
        $this->assertStringContainsString('data-error-field="gender"', $html);
        $this->assertStringContainsString('data-error-field="contacts.phone"', $html);
        $this->assertStringNotContainsString('error.messages.join', $html);
        $this->assertStringNotContainsString('<tr x-show="rowErrors(row).length"', $html);
        $this->assertStringContainsString('novalidate', $html);
        $this->assertStringContainsString('isContactFieldRequired', $html);
        $this->assertStringContainsString('Loading tenants...', $html);
        $this->assertStringContainsString('sticky bottom-0', $html);
        $this->assertStringNotContainsString('pagination', $html);
        $this->assertStringContainsString('open-tenant-bulk-discard', $html);
        $this->assertStringContainsString('Discard all unsaved changes on this page?', $html);
        $this->assertStringNotContainsString("confirm('Discard all unsaved changes", $html);
        $this->assertStringContainsString('Preview IC Photo', $html);
        $this->assertStringContainsString('Payment Receipt', Blade::render('<x-modals.receipt-preview-modal />'));
        foreach (['name', 'relationship', 'phone'] as $field) {
            $this->assertMatchesRegularExpression('/<input\b[^>]*x-model="row\.contacts\[row\.activeContactIndex\]\.'.preg_quote($field, '/').'"[^>]*>/s', $html);
        }
    }
}
