<?php

namespace Tests\Feature\Auth;

use App\Models\DocumentTemplate;
use App\Models\FeeType;
use App\Models\RefCodePackage;
use App\Models\User;
use App\Services\AccountDefaultsService;
use App\Services\AccountPermissionService;
use Database\Seeders\FeeTypeSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationDefaultsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_01_03_144036_create_document_templates_table.php',
            '2026_04_12_074232_add_new_column_to_users_table.php',
            '2026_06_21_233437_create_audit_logs_table.php',
            '2026_01_03_042335_create_ref_code_packages_table.php',
            '0002_01_07_042334_create_user_management_table.php',
            '2026_02_23_134219_add_soft_deletes_to_user_management_table.php',
            '2026_01_03_144458_create_fee_type_table.php',
            '2026_11_07_000001_add_account_permissions_to_users_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        Notification::fake();
        User::factory()->create(['role' => 'admin']);
        RefCodePackage::create([
            'ref_code' => 'STARTER', 'name' => 'Starter', 'price' => 0,
            'price_mode' => 'monthly', 'commission_rate' => 0, 'status' => 'active',
        ]);
    }

    public static function adminRoles(): array
    {
        return [
            'agent' => ['agent', null, 'agentAdmin'],
            'self-managing owner' => ['owner', 'no', 'ownerAdmin'],
        ];
    }

    #[DataProvider('adminRoles')]
    public function test_registration_creates_private_templates_and_makes_standard_fees_available(
        string $role, ?string $hasAgent, string $expectedRole
    ): void {
        $response = $this->post('/register', $this->registrationData($role, $hasAgent));

        $response->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $this->assertAuthenticated();
        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame($expectedRole, $user->role);
        $this->assertSame(AccountPermissionService::names(), $user->account_permissions);
        foreach (AccountPermissionService::names() as $permission) {
            $this->assertTrue(Gate::forUser($user)->allows($permission), $permission);
        }
        $this->assertFalse(Gate::forUser($user)->allows('super-admin'));
        $templates = DocumentTemplate::where('user_id', $user->id)->get()->keyBy('category');
        $this->assertCount(3, $templates);
        foreach (['invoice', 'receipt', 'refund'] as $category) {
            $this->assertSame('active', $templates[$category]->status);
            $this->assertSame($user->id, $templates[$category]->created_by);
            $this->assertFalse((bool) $templates[$category]->is_system_default);
            $this->assertSame(File::get(resource_path("document-templates/defaults/{$category}.html")), $templates[$category]->html_template);
        }
        $this->assertDatabaseMissing('document_templates', ['user_id' => $user->id, 'category' => 'agreement']);
        $this->assertDatabaseHas('user_management', ['user_id' => $user->id, 'subscription_status' => 'active']);
        $this->assertSame(10, FeeType::where('is_system', true)->where('is_active', true)->count());
    }

    public function test_repeated_setup_preserves_edits_and_does_not_duplicate_shared_fees(): void
    {
        $user = User::factory()->create(['role' => 'agentAdmin']);
        $service = app(AccountDefaultsService::class);
        $service->initialize($user);
        $template = DocumentTemplate::where('user_id', $user->id)->where('category', 'invoice')->firstOrFail();
        $template->update(['html_template' => '<p>My invoice</p>', 'status' => 'inactive']);
        FeeType::where('name', 'Monthly Rental')->update(['is_active' => false]);

        $service->initialize($user);
        $this->seed(FeeTypeSeeder::class);
        $otherUser = User::factory()->create(['role' => 'ownerAdmin']);
        $service->initialize($otherUser);

        $this->assertSame(3, DocumentTemplate::where('user_id', $user->id)->count());
        $this->assertSame(3, DocumentTemplate::where('user_id', $otherUser->id)->count());
        $this->assertSame('<p>My invoice</p>', $template->fresh()->html_template);
        $this->assertSame('inactive', $template->fresh()->status);
        $this->assertSame(10, FeeType::count());
        $this->assertDatabaseHas('fee_types', ['name' => 'Monthly Rental', 'is_active' => false]);
        $this->assertNotSame($template->id, DocumentTemplate::where('user_id', $otherUser->id)->where('category', 'invoice')->value('id'));
    }

    public function test_registration_preserves_existing_account_permission_restrictions(): void
    {
        $user = User::factory()->create([
            'email' => 'new@example.com', 'role' => 'agentAdmin', 'status' => 'inactive',
        ]);
        $user->forceFill(['account_permissions' => ['dashboard.tab']])->save();

        $response = $this->post('/register', $this->registrationData('agent'));

        $response->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $this->assertSame(['dashboard.tab'], $user->fresh()->account_permissions);
        $this->assertFalse(Gate::forUser($user->fresh())->allows('invoice.record payment'));
    }

    public function test_invalid_registration_creates_no_defaults(): void
    {
        $response = $this->post('/register', array_merge($this->registrationData('agent'), ['ref_code' => 'INVALID']));

        $response->assertSessionHasErrors('ref_code');
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->assertSame(0, DocumentTemplate::count());
        $this->assertSame(0, FeeType::count());
    }

    public function test_setup_failure_rolls_back_account_subscription_and_templates(): void
    {
        $this->withoutExceptionHandling();
        File::shouldReceive('get')->once()->andThrow(new \RuntimeException('Template unavailable'));

        try {
            $this->post('/register', $this->registrationData('agent'));
            $this->fail('Registration should fail when a required template cannot be loaded.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Template unavailable', $exception->getMessage());
        }

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->assertSame(0, DB::table('user_management')->count());
        $this->assertSame(0, DocumentTemplate::count());
        $this->assertGuest();
        Notification::assertNothingSent();
    }

    private function registrationData(string $role, ?string $hasAgent = null): array
    {
        return array_filter([
            'name' => 'New Manager', 'email' => 'new@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
            'role' => $role, 'has_agent' => $hasAgent, 'ref_code' => 'STARTER', 'terms' => '1',
        ], fn ($value) => $value !== null);
    }
}
