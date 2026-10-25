<?php

namespace Tests\Feature;

use App\FeeTypeCategory;
use App\Http\Controllers\TenantsController;
use App\Models\{FeeType, Lease, LeaseCharge, Property, Room, Unit};
use App\Services\{FileService, LeaseService};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Storage, Validator};
use Tests\TestCase;

class LeaseAgreementRegressionTest extends TestCase
{
    private function lease(): Lease
    {
        $property = new Property(['address' => 'Long Street', 'city' => 'KL', 'postcode' => '50000', 'type' => 'Condo']);
        $unit = (new Unit(['unit_no' => 'A-12-08']))->setRelation('property', $property)->setRelation('owner', null);
        $room = (new Room(['room_no' => 'R3', 'room_type' => 'Master Room']))->setRelation('unit', $unit)->setRelation('owner', null);
        return (new Lease(['leasable_type' => Room::class]))->setRelation('leasable', $room)
            ->setRelation('tenant', null)->setRelation('charges', collect());
    }

    private function charge(string $name, FeeTypeCategory $category, int $amount, string $frequency = 'monthly'): LeaseCharge
    {
        return (new LeaseCharge(['description' => $name, 'amount' => $amount, 'frequency' => $frequency]))
            ->setRelation('feeType', new FeeType(['name' => $name, 'category' => $category]));
    }

    public function test_breakdown_basis_uses_exact_rent_cents_and_unique_frequency_only(): void
    {
        $service = app(LeaseService::class);
        foreach (['daily', 'monthly', 'yearly'] as $frequency) {
            $lease = $this->lease()->setRelation('charges', collect([
                $this->charge('Agreement Fee', FeeTypeCategory::MANAGEMENT, 8000, 'one_time'),
                $this->charge('Rent A', FeeTypeCategory::RENT, 30000, $frequency),
                $this->charge('Deposit', FeeTypeCategory::DEPOSIT, 100000),
                $this->charge('Rent B', FeeTypeCategory::RENT, 20000, $frequency),
            ]));
            $before = $lease->toArray();
            $this->assertSame(['cents' => 50000, 'frequency' => $frequency, 'error' => null], $service->getAgreementRentalBasis($lease));
            $this->assertSame($before, $lease->toArray());
            $lease->charges[1]->amount = 30001;
            $this->assertSame(50001, $service->getAgreementRentalBasis($lease)['cents']);
            $lease->charges[1]->frequency = $frequency === 'daily' ? 'yearly' : 'daily';
            $this->assertNull($service->getAgreementRentalBasis($lease)['cents']);
            $this->assertStringContainsString('multiple frequencies', $service->getAgreementRentalBasis($lease)['error']);
        }
        $lease = $this->lease();
        $this->assertNotNull($service->getAgreementRentalBasis($lease)['error']);
        $lease->setRawAttributes(['rent_price' => 50001, 'term_type' => 'yearly']);
        $this->assertSame(['cents' => 50001, 'frequency' => 'yearly', 'error' => null], $service->getAgreementRentalBasis($lease));
        $lease->setRelation('charges', collect([$this->charge('Deposit', FeeTypeCategory::DEPOSIT, 100000)]));
        $this->assertNull($service->getAgreementRentalBasis($lease)['cents']);
        foreach ([null, -1] as $amount) {
            $charge = $this->charge('Rent', FeeTypeCategory::RENT, 1);
            $charge->amount = $amount;
            $lease->setRelation('charges', collect([$charge]));
            $this->assertNotNull($service->getAgreementRentalBasis($lease)['error']);
        }
    }

    public function test_agreement_uses_lease_charges_and_parent_unit_without_mutating_models(): void
    {
        $lease = $this->lease();
        $lease->setRelation('charges', collect([
            $this->charge('Monthly Rental', FeeTypeCategory::RENT, 123456),
            $this->charge('Security Deposit', FeeTypeCategory::DEPOSIT, 246912),
            $this->charge('Utilities Deposit', FeeTypeCategory::DEPOSIT, 50000),
            $this->charge('Management Fee', FeeTypeCategory::MANAGEMENT, 10000),
        ]));
        $before = $lease->toArray();
        $vars = app(LeaseService::class)->getAgreementVariables($lease);
        $this->assertSame('A-12-08', $vars['unit_number']);
        $this->assertSame('(A-12-08), Long Street, 50000 KL', $vars['property_address']);
        $this->assertSame('1,234.56', $vars['rent_price']);
        $this->assertSame('2,469.12', $vars['security_deposit']);
        $this->assertSame('500.00', $vars['utilities_deposit']);
        $this->assertSame('Master Room', $vars['leasable_type']);
        $this->assertSame('Master Room', $vars['leasalable_type']);
        $this->assertSame('Master Room', $vars['service_package']);
        $this->assertSame('Room', $vars['property_type']);
        $this->assertSame($before, $lease->toArray());
    }

    public function test_service_package_description_uses_only_the_assets_own_type_and_preserves_default(): void
    {
        $service = app(LeaseService::class);
        $default = 'CUSTOMER SERVICE (9AM-5.30PM), INDAH WATER,FACILITY ALLOWANCE, CCTV, FACILITY ROOM &HIGH SPEED WIFI';
        $wifi = 'CUSTOMER SERVICE (9AM-5.30PM), INDAH WATER, FACILITY ALLOWANCE & HIGH SPEED WIFI';
        $standard = 'CUSTOMER SERVICE (9AM-5.30PM), INDAH WATER & FACILITY ALLOWANCE';
        $lease = $this->lease();
        $room = $lease->leasable;
        $unit = $room->unit;
        $property = $unit->property->setRelation('owner', null);
        foreach ([[$property, 'type'], [$unit, 'type'], [$room, 'room_type']] as [$asset, $field]) {
            // Conflicting ancestor values must never provide a fallback.
            $property->type = 'WIFI UNIT';
            $unit->type = 'STANDARD UNIT';
            $lease->setRelation('leasable', $asset);
            foreach ([['WIFI UNIT', $wifi], [' wifi unit ', $wifi], ['STANDARD UNIT', $standard],
                ["\tStAnDaRd UnIt\n", $standard], ['Other', $default], [null, $default], ['', $default], ['   ', $default]] as [$type, $expected]) {
                $asset->$field = $type;
                $before = $lease->toArray();
                $variables = $service->getAgreementVariables($lease);
                $this->assertSame($expected, $variables['service_package_description']);
                $this->assertSame('Not recorded', $variables['rent_price']);
                $this->assertSame($before, $lease->toArray());
            }
        }
        $seed = file_get_contents(database_path('seeders/AntzCustomTemplate.php'));
        $this->assertSame(1, substr_count($seed, '{{ service_package_description }}'));
        $this->assertStringContainsString('<td id="i75opp">{{ service_package_description }}</td>', $seed);
    }

    public function test_property_unit_missing_legacy_and_nonmonthly_values(): void
    {
        $lease = $this->lease();
        $service = app(LeaseService::class);
        $this->assertSame('Not recorded', $service->getAgreementVariables($lease)['rent_price']);
        $lease->setRawAttributes(['rent_price' => 123456, 'security_deposit' => 20000]);
        $this->assertSame('1,234.56', $service->getAgreementVariables($lease)['rent_price']);
        $this->assertSame('200.00', $service->getAgreementVariables($lease)['security_deposit']);
        $unit = $lease->leasable->unit;
        $lease->setRelation('leasable', $unit);
        $this->assertSame('A-12-08', $service->getAgreementVariables($lease)['unit_no']);
        $this->assertSame('Not recorded', $service->getAgreementVariables($lease)['service_package']);
        $lease->setRelation('leasable', $unit->property->setRelation('owner', null));
        $this->assertSame('N/A (whole property)', $service->getAgreementVariables($lease)['unit_number']);
        $this->assertSame('Condo', $service->getAgreementVariables($lease)['service_package']);
        $lease->leasable->type = null;
        $this->assertSame('Not recorded', $service->getAgreementVariables($lease)['service_package']);
        $unit->setRelation('property', null);
        $lease->setRelation('leasable', $unit);
        $this->assertSame('Not recorded', $service->getAgreementVariables($lease)['service_package']);
        $lease->setRelation('charges', collect([$this->charge('Daily Rental', FeeTypeCategory::RENT, 10000, 'daily')]));
        $this->assertSame('100.00 (daily)', $service->getAgreementVariables($lease)['rent_price']);
    }

    public function test_agreement_address_normalizes_only_the_unit_prefix_without_changing_source_data(): void
    {
        $service = app(LeaseService::class);
        $street = 'JALAN KAMPAR BARAT (1), TAMAN KAMPAR BARAT';
        $expected = '(51-2-4), '.$street.', 31900 KAMPAR, PERAK.';
        foreach (['51-2-4', '(51-2-4)', ' ((51-2-4)) '] as $number) {
            foreach (['', '(51-2-4), ', '51-2-4, ', 'Unit 51-2-4, ', '(51-2-4), 51-2-4, '] as $prefix) {
                $lease = $this->lease();
                $unit = $lease->leasable->unit;
                $unit->unit_no = $number;
                $unit->property->fill(['address' => $prefix.$street, 'postcode' => '31900', 'city' => 'KAMPAR', 'state' => 'PERAK.']);
                $before = $lease->toArray();
                $this->assertSame($expected, $service->getAgreementVariables($lease)['property_address']);
                $this->assertSame($before, $lease->toArray());
                $lease->setRelation('leasable', $unit);
                $this->assertSame($expected, $service->getAgreementVariables($lease)['property_address']);
            }
        }
        $lease = $this->lease();
        $unit = $lease->leasable->unit;
        foreach ([null, '', '  ', '()'] as $number) {
            $unit->unit_no = $number;
            $this->assertSame('Long Street, 50000 KL', $service->getAgreementVariables($lease)['property_address']);
        }
        $unit->unit_no = '51-2-4';
        $unit->property->address = '51-2-40, JALAN (51-2-4)';
        $this->assertSame('(51-2-4), 51-2-40, JALAN (51-2-4), 50000 KL', $service->getAgreementVariables($lease)['property_address']);
        $lease->setRelation('leasable', $unit->property->setRelation('owner', null));
        $this->assertSame('51-2-40, JALAN (51-2-4), 50000 KL', $service->getAgreementVariables($lease)['property_address']);
    }

    public function test_each_asset_uses_its_own_distinct_type_and_unit_never_falls_back(): void
    {
        $lease = $this->lease();
        $room = $lease->leasable;
        $unit = $room->unit;
        $property = $unit->property->setRelation('owner', null);
        // Simulate hydrated database attributes; Unit.type need not be mass assignable to read it.
        $unit->setRawAttributes(array_merge($unit->getAttributes(), ['type' => 'Studio']));
        $service = app(LeaseService::class);
        foreach ([[$property, 'Condo'], [$unit, 'Studio'], [$room, 'Master Room']] as [$asset, $expected]) {
            $lease->setRelation('leasable', $asset);
            $before = $lease->toArray();
            $vars = $service->getAgreementVariables($lease);
            foreach (['service_package', 'leasable_type', 'leasalable_type'] as $token) {
                $this->assertSame($expected, $vars[$token]);
            }
            $this->assertSame($before, $lease->toArray());
        }
        $lease->setRelation('leasable', $unit);
        foreach ([null, '', '   '] as $missing) {
            $unit->type = $missing;
            $vars = $service->getAgreementVariables($lease);
            foreach (['service_package', 'leasable_type', 'leasalable_type'] as $token) {
                $this->assertSame('Not recorded', $vars[$token]);
            }
        }
        $unit->type = 'Studio';
        $unit->setRelation('property', null);
        $this->assertSame('Studio', $service->getAgreementVariables($lease)['service_package']);
    }

    public function test_room_package_never_falls_back_to_property_and_missing_types_are_explicit(): void
    {
        $lease = $this->lease();
        $room = $lease->leasable;
        $service = app(LeaseService::class);
        foreach ([null, '', '   '] as $missing) {
            $room->room_type = $missing;
            $vars = $service->getAgreementVariables($lease);
            foreach (['service_package', 'leasable_type', 'leasalable_type'] as $token) {
                $this->assertSame('Not recorded', $vars[$token]);
            }
        }
        $room->room_type = 'Single Room';
        $room->setRelation('unit', null);
        $this->assertSame('Single Room', $service->getAgreementVariables($lease)['service_package']);
        $property = (new Property(['type' => 'Landed']))->setRelation('owner', null);
        $lease->setRelation('leasable', $property);
        foreach (['Landed', null, '', '   '] as $type) {
            $property->type = $type;
            $vars = $service->getAgreementVariables($lease);
            foreach (['service_package', 'leasable_type', 'leasalable_type'] as $token) {
                $this->assertSame(filled($type) ? $type : 'Not recorded', $vars[$token]);
            }
        }
    }

    public function test_combined_deposit_is_not_misrepresented_as_security_alone(): void
    {
        $lease = $this->lease()->setRelation('charges', collect([
            $this->charge('Security & Utilities Deposit', FeeTypeCategory::DEPOSIT, 30000),
        ]));
        $vars = app(LeaseService::class)->getAgreementVariables($lease);
        $this->assertSame('Not recorded', $vars['security_deposit']);
        $this->assertSame('300.00', $vars['total_deposit']);
    }

    public function test_real_image_and_pdf_are_validated_stored_and_streamed_privately(): void
    {
        Storage::fake('local');
        $files = [
            UploadedFile::fake()->createWithContent('ic.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"),
            UploadedFile::fake()->createWithContent('ic.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')),
            UploadedFile::fake()->image('ic.jpg'),
            UploadedFile::fake()->image('ic.gif'),
        ];
        foreach ($files as $fake) {
            $file = new UploadedFile($fake->getRealPath(), $fake->getClientOriginalName(), null, null, true);
            $this->assertTrue(Validator::make(['file' => $file], ['file' => TenantsController::IC_DOCUMENT_RULES])->passes());
            $service = app(FileService::class);
            $path = $service->upload($file, 'test-user', 'tenant_ic');
            $this->assertStringStartsWith('uploads/user_test-user/tenants/ic_photo/', $path);
            Storage::disk('local')->assertExists($path);
            $response = $service->getStreamResponse($path);
            $this->assertSame($file->getMimeType(), $response->headers->get('Content-Type'));
            ob_start();
            $response->sendContent();
            $this->assertSame(file_get_contents($file->getRealPath()), ob_get_clean());
        }
        foreach (['ic.pdf', 'ic.jpg', 'ic.html'] as $name) {
            $fake = UploadedFile::fake()->createWithContent($name, '<html>Not an identity document</html>');
            $file = new UploadedFile($fake->getRealPath(), $name, null, null, true);
            $this->assertTrue(Validator::make(['file' => $file], ['file' => TenantsController::IC_DOCUMENT_RULES])->fails());
        }
        $oversized = UploadedFile::fake()->createWithContent('large.pdf', "%PDF-1.4\n".str_repeat('x', 2048 * 1024));
        $this->assertTrue(Validator::make(['file' => $oversized], ['file' => TenantsController::IC_DOCUMENT_RULES])->fails());
    }
}
