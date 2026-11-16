<?php

namespace Tests\Feature;

use App\Services\BankAccountService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Crypt, DB, Schema};
use Tests\TestCase;

class SubscriptionBankDetailsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('role');
            $table->timestamps();
        });
        Schema::create('bank_details', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('bank_name');
            $table->string('account_holder_name');
            $table->text('account_number');
        });
        Schema::create('bank_user', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('bank_detail_id');
            $table->boolean('is_default');
            $table->timestamps();
        });
        DB::table('users')->insert([
            ['id' => 'admin', 'role' => 'admin', 'created_at' => '2026-01-01'],
            ['id' => 'manager', 'role' => 'ownerAdmin', 'created_at' => '2025-01-01'],
        ]);
    }

    private function bank(string $id, string $userId, bool $default): void
    {
        DB::table('bank_details')->insert([
            'id' => $id, 'bank_name' => 'Bank '.$id,
            'account_holder_name' => 'Holder '.$id,
            'account_number' => Crypt::encryptString('1234567890'),
        ]);
        DB::table('bank_user')->insert([
            'id' => $id, 'bank_detail_id' => $id, 'user_id' => $userId, 'is_default' => $default,
        ]);
    }

    public function test_subscription_uses_only_system_admin_default_bank_and_decrypts_account_number(): void
    {
        $this->bank('manager-bank', 'manager', true);
        $this->bank('admin-other-bank', 'admin', false);
        $this->bank('admin-default-bank', 'admin', true);

        $bank = app(BankAccountService::class)->getDefaultForSystemAdmin();

        $this->assertSame('admin-default-bank', $bank->id);
        $this->assertSame('Bank admin-default-bank', $bank->bank_name);
        $this->assertSame('Holder admin-default-bank', $bank->account_holder_name);
        $this->assertSame('1234567890', $bank->account_number);
        $this->assertArrayNotHasKey('account_number', $bank->toArray());
    }

    public function test_missing_default_does_not_fall_back_to_another_account(): void
    {
        $this->bank('manager-bank', 'manager', true);
        $this->bank('admin-other-bank', 'admin', false);

        $this->assertNull(app(BankAccountService::class)->getDefaultForSystemAdmin());
    }

    public function test_missing_system_admin_returns_no_bank_details(): void
    {
        DB::table('users')->where('id', 'admin')->delete();
        $this->bank('manager-bank', 'manager', true);

        $this->assertNull(app(BankAccountService::class)->getDefaultForSystemAdmin());
    }
}
