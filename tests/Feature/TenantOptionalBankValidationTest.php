<?php

namespace Tests\Feature;

use App\Http\Controllers\TenantsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class TenantOptionalBankValidationTest extends TestCase
{
    private function bankValidator(array $values)
    {
        $request = new class($values + ['tenant_type' => 'individual', 'identity_type' => 'ic', 'phone' => '0123456789', 'emergency_contacts' => []]) extends Request {
            public array $capturedRules = [];

            public function validate(array $rules, ...$arguments): array
            {
                $this->capturedRules = $rules;
                return [];
            }
        };
        (new ReflectionMethod(TenantsController::class, 'validateTenantData'))->invoke(new TenantsController(), $request, null, null, true);

        return Validator::make($values, array_intersect_key($request->capturedRules, array_flip(['bank_name', 'bank_code', 'account_holder_name', 'account_number'])));
    }

    public function test_bank_details_can_be_omitted_entirely(): void
    {
        $this->assertFalse($this->bankValidator([])->fails());
        $this->assertFalse($this->bankValidator(['bank_name' => null, 'account_holder_name' => null, 'account_number' => null])->fails());
    }

    public function test_provided_bank_account_requires_complete_details_and_numeric_account_number(): void
    {
        $this->assertTrue($this->bankValidator(['bank_name' => 'Example Bank'])->fails());
        $values = ['bank_name' => 'Example Bank', 'account_holder_name' => 'Tenant', 'account_number' => '00123456'];
        $this->assertFalse($this->bankValidator($values)->fails());
        $values['account_number'] = 'ABC123';
        $this->assertTrue($this->bankValidator($values)->fails());
    }
}
