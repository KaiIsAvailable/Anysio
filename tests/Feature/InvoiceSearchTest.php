<?php

namespace Tests\Feature;

use App\Models\{Invoice, Property, Room, Unit};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        foreach ([
            'users' => ['name'], 'tenants' => ['user_id'],
            'properties' => ['name'], 'units' => ['unit_no'], 'rooms' => ['room_no'],
            'leases' => ['tenant_id', 'leasable_type', 'leasable_id'],
            'invoices' => ['lease_id', 'user_id', 'invoice_no', 'period', 'due_date', 'status', 'remarks'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($name, $columns) {
                $table->string('id')->primary();
                foreach ($columns as $column) $table->string($column)->nullable();
                if ($name === 'invoices') {
                    $table->softDeletes();
                    foreach (['total_amount', 'amount_paid', 'amount_balance'] as $column) {
                        $table->bigInteger($column)->default(0);
                    }
                }
            });
        }
        DB::table('users')->insert(['id' => 'alice', 'name' => "Alice O'Connor"]);
        DB::table('tenants')->insert(['id' => 'tenant', 'user_id' => 'alice']);
        foreach ([Property::class => ['properties', 'name', 'Sunrise Residence'],
            Unit::class => ['units', 'unit_no', 'A-12-08'], Room::class => ['rooms', 'room_no', 'Master-101'],
        ] as $type => [$table, $column, $label]) {
            DB::table($table)->insert(['id' => $table, $column => $label]);
            DB::table('leases')->insert(['id' => $table, 'tenant_id' => 'tenant',
                'leasable_type' => $type, 'leasable_id' => $table]);
            DB::table('invoices')->insert(['id' => $table, 'lease_id' => $table,
                'invoice_no' => 'INV-'.$table, 'period' => '2026-11-01', 'due_date' => '2026-12-15',
                'total_amount' => 123450, 'amount_paid' => 23450, 'amount_balance' => 100000,
                'status' => 'partial', 'remarks' => 'Special arrangement']);
        }
        DB::table('invoices')->insert(['id' => 'other', 'invoice_no' => 'OTHER',
            'status' => 'unpaid', 'total_amount' => 1234, 'remarks' => 'Private']);
    }

    public static function terms(): array
    {
        return array_map(fn ($term) => [$term], [
            'INV-', 'Alice', "O'Connor", '11/2026', '2026-11', '15/12/2026', '2026-12-15',
            '1,234.50', 'RM 1,234.50', '234.50', '1000', 'Partial', 'arrangement', '  Alice  ',
        ]);
    }

    #[DataProvider('terms')]
    public function test_searches_displayed_invoice_fields(string $term): void
    {
        $this->assertEqualsCanonicalizing(['properties', 'units', 'rooms'],
            Invoice::search($term)->pluck('id')->all());
    }

    public function test_searches_each_leasable_label_and_type(): void
    {
        foreach (['Sunrise' => 'properties', 'Property' => 'properties', 'A-12' => 'units',
            'Unit' => 'units', 'Master' => 'rooms', 'Room' => 'rooms'] as $term => $expected) {
            $this->assertSame([$expected], Invoice::search($term)->pluck('id')->all(), $term);
        }
    }

    public function test_search_stays_inside_existing_constraints_and_excludes_deleted_invoices(): void
    {
        $this->assertSame([], Invoice::whereKey('other')->search('Alice')->pluck('id')->all());
        DB::table('invoices')->where('id', 'rooms')->update(['deleted_at' => now()]);
        $this->assertCount(2, Invoice::search('Alice')->get());
    }

    public function test_empty_zero_unknown_and_paid_searches(): void
    {
        $this->assertSame(4, Invoice::search('  ')->count());
        $this->assertTrue(Invoice::whereKey('other')->search('0')->exists());
        $this->assertSame(0, Invoice::search('no matching invoice')->count());
        $this->assertSame(0, Invoice::search('paid')->count());
        $this->assertSame(['other'], Invoice::search('unpaid')->pluck('id')->all());
        DB::table('invoices')->where('id', 'other')->update(['status' => 'paid']);
        $this->assertSame(['other'], Invoice::search('PAID')->pluck('id')->all());
    }
}
