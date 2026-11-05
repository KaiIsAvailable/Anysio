<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantCompanyServiceTest extends TestCase
{
    public function test_company_creation_and_edit_preserve_profile_fields_and_replace_certificate(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('companies', function (Blueprint $table) {
            $table->string('id')->primary();
            foreach (['name', 'ssm_new_number', 'ssm_old_number', 'address', 'phone', 'email', 'logo_path', 'ssm_cert_path'] as $field) {
                $table->string($field)->nullable();
            }
            $table->timestamps();
        });
        Schema::create('company_user', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('company_id');
            $table->string('user_id');
            $table->unique(['company_id', 'user_id']);
            $table->timestamps();
        });
        Storage::fake('local');
        Storage::fake('public');
        $user = new User();
        $user->id = (string) Str::ulid();
        $user->exists = true;

        Model::withoutEvents(function () use ($user) {
            $service = app(CompanyService::class);
            $company = $service->saveForTenant($user, [
                'company_name' => 'Example Ltd', 'ssm_new_number' => '202601234567', 'company_address' => 'Old address', 'ssm_old_number' => 'OLD123', 'company_phone' => '12345', 'company_email' => 'company@example.com',
            ], UploadedFile::fake()->create('certificate.pdf', 10, 'application/pdf'), UploadedFile::fake()->create('logo.png', 10, 'image/png'));
            $oldPath = $company->ssm_cert_path;
            Storage::disk('public')->assertExists($company->logo_path);
            $this->assertSame('company@example.com', $company->email);
            $this->assertSame('12345', $company->phone);
            $this->assertSame('OLD123', $company->ssm_old_number);
            Storage::disk('local')->assertExists($oldPath);
            $this->assertSame($company->id, $user->companies()->first()->id);
            $company->update(['phone' => '12345', 'ssm_old_number' => 'OLD123']);

            $updated = $service->saveForTenant($user, [
                'company_name' => 'Renamed Ltd', 'ssm_new_number' => '202601234567', 'company_address' => 'New address',
            ]);
            $this->assertSame($oldPath, $updated->ssm_cert_path);
            $this->assertSame('12345', $updated->phone);
            $this->assertSame('OLD123', $updated->ssm_old_number);
            $this->assertSame('New address', $updated->address);
            $this->assertSame('Renamed Ltd', $updated->name);

            $updated = $service->saveForTenant($user, [
                'company_name' => 'Renamed Ltd', 'ssm_new_number' => '202601234567', 'company_address' => 'New address',
            ], UploadedFile::fake()->create('replacement.pdf', 10, 'application/pdf'));
            Storage::disk('local')->assertMissing($oldPath);
            Storage::disk('local')->assertExists($updated->ssm_cert_path);
            $this->assertSame(1, Company::count());
            $this->assertSame(1, DB::table('company_user')->count());
        });
    }
}
