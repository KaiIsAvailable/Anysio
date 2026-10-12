<?php

namespace Tests\Feature;

use App\Models\{Invoice, Lease, Property, User};
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TenantUiTest extends TestCase
{
    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    public static function invoiceStates(): array
    {
        return [
            ['partial', 500, 500, '2026-10-19', 'Partially Paid', 'Overdue'],
            ['partial', 500, 500, '2026-10-20', 'Partially Paid', 'Due Today'],
            ['partial', 500, 500, '2026-10-21', 'Partially Paid', null],
            ['unpaid', 1000, 0, '2026-10-19', 'Unpaid', 'Overdue'],
            ['unpaid', 1000, 0, '2026-10-20', 'Unpaid', 'Due Today'],
            ['paid', 0, 1000, '2026-10-19', 'Paid', null],
            ['paid', 100, 1000, '2026-10-19', 'Paid', null],
            ['void', 1000, 0, '2026-10-19', 'Void', null],
            ['void', 1000, 0, '2026-10-20', 'Void', null],
            ['partial', 0, 1000, '2026-10-19', 'Partially Paid', null],
            ['unpaid', 1000, 0, null, 'Unpaid', null],
            ['overdue', 500, 500, '2026-10-19', 'Partially Paid', 'Overdue'],
            ['overdue', 0, 1000, '2026-10-19', 'Paid', null],
            ['overdue', 1000, 0, '2026-10-20', 'Unpaid', 'Due Today'],
        ];
    }

    #[DataProvider('invoiceStates')]
    public function test_payment_and_date_badges_are_independent_and_do_not_change_invoice_data($status, $balance, $paid, $due, $label, $dateLabel): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 23:59:59', 'Asia/Kuala_Lumpur'));
        $invoice = new Invoice(['status' => $status, 'amount_balance' => $balance, 'amount_paid' => $paid, 'due_date' => $due]);
        $attributes = $invoice->getAttributes();
        $html = Blade::render('<x-tenant.invoice-status :invoice="$invoice" />', compact('invoice'));
        $this->assertStringContainsString('>'.$label.'</span>', $html);
        $this->assertSame($dateLabel === 'Overdue' ? 1 : 0, substr_count($html, '>Overdue</span>'));
        $this->assertSame($dateLabel === 'Due Today' ? 1 : 0, substr_count($html, '>Due Today</span>'));
        if ($dateLabel === 'Overdue') $this->assertMatchesRegularExpression('/text-red-700[^>]*>Overdue<\/span>/', $html);
        $this->assertSame($attributes, $invoice->getAttributes());
    }

    public function test_due_today_changes_to_overdue_only_at_kuala_lumpur_midnight(): void
    {
        $invoice = new Invoice(['status' => 'partial', 'amount_balance' => 500, 'due_date' => '2026-10-20']);
        foreach (['2026-10-19 16:00:00' => 'Due Today', '2026-10-20 15:59:59' => 'Due Today', '2026-10-20 16:00:00' => 'Overdue'] as $utc => $label) {
            Carbon::setTestNow(Carbon::parse($utc, 'UTC'));
            $html = Blade::render('<x-tenant.invoice-status :invoice="$invoice" />', compact('invoice'));
            $this->assertStringContainsString('>'.$label.'</span>', $html);
        }
    }

    /** Render page content with fixture data, excluding navigation/layout database queries. */
    private function content(string $view, array $data): string
    {
        $source = file_get_contents(view($view)->getPath());
        $source = preg_replace('/<x-app-layout>|<\/x-app-layout>|<x-slot name="header">.*?<\/x-slot>/s', '', $source);
        return Blade::render($source, $data);
    }

    public function test_lease_desktop_row_and_mobile_card_have_keyboard_navigation_and_no_action_column(): void
    {
        $property = new Property(['name' => 'Sunrise Apartment']);
        $property->setRelation('owner', new User(['name' => 'Owner']));
        $lease = new Lease(['leasable_type' => Property::class, 'status' => 'New', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $lease->id = 'own-lease'; $lease->setRelation('leasable', $property); $lease->setRelation('charges', collect());
        $html = $this->content('tenantSide.tenants.leases.index', ['leases' => collect([$lease])]);
        $this->assertSame(2, substr_count($html, 'data-tenant-lease-url="'.route('tenants.leases.show', $lease).'"'));
        $this->assertSame(2, substr_count($html, 'tabindex="0" role="link"'));
        $this->assertStringNotContainsString('View Details', $html);
        $this->assertStringNotContainsString('View Lease Details', $html);
        $this->assertDoesNotMatchRegularExpression('/<th[^>]*>\s*Actions?\s*<\/th>/', $html);
        $this->assertSame(5, preg_match_all('/<th\s/', $html));
        $this->assertSame(5, preg_match_all('/<td\s/', $html));
        $this->assertStringContainsString('cursor-pointer', $html);
        $this->assertStringContainsString('focus-visible:outline', $html);
    }

    public function test_invoice_mobile_and_desktop_both_render_payment_and_overdue_badges(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'Asia/Kuala_Lumpur'));
        $invoice = new Invoice(['invoice_no' => 'INV-TEST', 'status' => 'partial', 'due_date' => '2026-10-19', 'amount_balance' => 500, 'amount_paid' => 500, 'total_amount' => 1000]);
        $invoice->setRelation('items', collect()); $invoice->setRelation('transactions', collect()); $invoice->setRelation('documentTemplate', null);
        $this->mock(InvoiceService::class)->shouldReceive('getInvoiceVariables')->twice()->with($invoice)->andReturn([]);
        $html = $this->content('tenantSide.tenants.invoices.index', ['invoices' => new LengthAwarePaginator([$invoice], 1, 10)]);
        $this->assertSame(2, substr_count($html, '>Partially Paid</span>'));
        $this->assertSame(2, substr_count($html, '>Overdue</span>'));
        $this->assertSame('partial', $invoice->status);
    }

    public function test_dashboard_action_order_keeps_complaint_first(): void
    {
        $source = file_get_contents(resource_path('views/tenantSide/tenants/dashboard.blade.php'));
        $this->assertTrue(strpos($source, 'data-open-complaint') < strpos($source, "@include('tenantSide.tenants.partials.current-leases'"));
        $this->assertTrue(strpos($source, "@include('tenantSide.tenants.partials.current-leases'") < strpos($source, '>Unpaid'));
    }
}
