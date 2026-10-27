<?php

namespace App\Services;
use Exception;
use Illuminate\Support\Facades\Auth;
use App\Traits\RoleBasedDataTrait;
use App\Models\{Lease, LeaseCharge, FeeType, Property, Unit, Room, User, Tenants, Owners, DocumentTemplate};
use App\FeeTypeCategory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;

class LeaseService
{
    use RoleBasedDataTrait;

    public function __construct(
        protected InvoiceService $invoiceService
    ) {
    }

    /**
     * Process New, Renew, Check Out and End Agreement.
     */
    public function process(User $user, array $data): Lease
    {
        try {
            return DB::transaction(function () use ($user, $data) {
                $status = $data['status'];

                Log::channel('testing')->info('Lease status submission debug:', [
                    'status' => $data['status'] ?? null,
                    'all_data' => $data,
                ]);

                // Get the previous lease when required
                $oldLease = $this->getOldLease($data);

                // 💡 NEW CHECK: If status is 'Renew', verify that all associated invoices are paid
                if ($status === 'Renew' && $oldLease) {
                    // Assuming your Lease model has an 'invoices' relation and a status field like 'paid'
                    $hasUnpaidInvoices = $oldLease->invoices()
                        ->whereNotIn('status', ['paid', 'void'])
                        ->exists();

                    if ($hasUnpaidInvoices) {
                        throw ValidationException::withMessages([
                            'error' => 'This lease cannot be renewed because there are still unpaid invoices associated with it.',
                        ]);
                    }
                }

                // Only a NEW lease consumes the subscription limit
                if ($status === 'New') {
                    $this->checkLeaseLimit($user);
                }

                // Resolve property / unit / room and tenant
                $context = $this->resolveLeaseContext($data, $oldLease);

                // For Renew / Check Out / End Agreement,
                // the previous lease is no longer current
                if ($oldLease) {
                    $oldLease->update([
                        'is_current' => false,
                        'is_pending_renewal' => false,
                    ]);
                }

                // Update property / unit / room status
                $this->updateLeasableStatus(
                    $context['leasable'],
                    $status
                );

                // Create the new lease
                $newLease = $this->createLease(
                    $data,
                    $oldLease,
                    $context
                );

                
                // New and Renew require financial charges
                if (in_array($status, ['New', 'Renew'])) {

                    // 1. 建立租約的基準費用配置 (Charges)
                    $this->createLeaseCharges(
                        $newLease,
                        $data
                    );

                    // 🌟 2. 完美接合：利用 InvoiceService 自動生成首期帳單！
                    $this->invoiceService->createInitialInvoiceForLease(
                        $newLease, 
                        $user // Auth::user() 從 controller 傳進來的
                    );
                }

                Log::info('Lease processed successfully.', [
                    'status' => $status,
                    'lease_id' => $newLease->id,
                    'old_lease_id' => $oldLease?->id,
                ]);

                return $newLease;
            });
        } catch (\Throwable $e) {

            Log::error('Lease creation failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
                'data'    => $data,
            ]);

            throw $e;
        }
    }

    protected function getOldLease(array $data): ?Lease
    {
        if ($data['status'] === 'New') {
            return null;
        }

        return Lease::with('leasable')->findOrFail($data['lease_id']);
    }

    protected function checkLeaseLimit(User $user): void
    {
        if ($user->role === 'admin') {
            return;
        }

        $management = $user->user_management;

        if (!$management || !$management->package) {
            throw ValidationException::withMessages([
                'error' => 'You do not have an active subscription package.',
            ]);
        }

        $package = $management->package;
        $baseLeaseLimit = (int) $package->base_lease;
        $extraLeaseLimit = (int) $management->extra_lease;
        $totalLeaseLimit = $baseLeaseLimit + $extraLeaseLimit;

        $currentLeaseCount = Lease::where('is_current', true)
            ->whereIn('status', ['New', 'Renew', 'End'])
            ->whereHas('tenant', function ($query) use ($user) {
                $query->where('created_by', $user->id);
            })
            ->count();

        if ($currentLeaseCount >= $totalLeaseLimit) {
            throw ValidationException::withMessages([
                'error' => "Limit Reached: Your current package ({$package->name}) only allows {$totalLeaseLimit} leases.",
            ]);
        }
    }

    protected function resolveLeaseContext(array $data, ?Lease $oldLease): array
    {
        if ($data['status'] !== 'New') {
            return [
                'leasable' => $oldLease->leasable,
                'leasable_type' => $oldLease->leasable_type,
                'leasable_id' => $oldLease->leasable_id,
                'tenant_id' => $oldLease->tenant_id,
            ];
        }

        $selection = $data['lease_selection'];

        $leasableType = match ($selection) {
            'property' => Property::class,
            'unit' => Unit::class,
            'room' => Room::class,
        };

        $leasableId = match ($selection) {
            'property' => $data['property_id'] ?? null,
            'unit' => $data['unit_id'] ?? null,
            'room' => $data['room_id'] ?? null,
        };

        if (!$leasableId) {
            throw ValidationException::withMessages([
                'lease_selection' => 'Please select a valid property, unit or room.',
            ]);
        }

        $leasable = $leasableType::find($leasableId);

        if (!$leasable) {
            throw ValidationException::withMessages([
                'lease_selection' => 'Property, unit or room not found.',
            ]);
        }

        return [
            'leasable' => $leasable,
            'leasable_type' => $leasableType,
            'leasable_id' => $leasableId,
            'tenant_id' => $data['tenant_id'],
        ];
    }

    protected function updateLeasableStatus($leasable, string $status): void
    {
        $targetStatus = match ($status) {
            'Check Out' => 'Cleaning',
            'End' => 'Vacant',
            default => 'Occupied',
        };

        $leasable->propagateStatus($targetStatus);

        $leasable->update([
            'status' => $targetStatus,
        ]);

        if (in_array($status, ['Check Out', 'End'])) {
            $leasable->syncStatus();
        }
    }

    protected function createLease(array $data, ?Lease $oldLease, array $context): Lease
    {
        $status = $data['status'];

        $startDate = in_array($status, ['New', 'Renew'])
            ? $this->parseDate($data['start_date'] ?? null)
            : $oldLease?->start_date;

        $endDate = in_array($status, ['New', 'Renew'])
            ? $this->parseDate($data['end_date'] ?? null)
            : $oldLease?->end_date;

        $checkedOutAt = $status === 'Check Out'
            ? $this->parseDate($data['checked_out_at'] ?? null)
            : null;

        $agreementEndedAt = $status === 'End'
            ? $this->parseDate($data['agreement_ended_at'] ?? null)
            : null;

        // Find if any charge fee type name contains daily, weekly, monthly, or yearly
        $termType = 'monthly'; // default fallback
        if (!empty($data['charges'])) {
            foreach ($data['charges'] as $charge) {
                $feeType = FeeType::find($charge['fee_type_id'] ?? null);
                if ($feeType) {
                    $name = strtolower($feeType->name);
                    if (str_contains($name, 'daily')) { $termType = 'daily'; break; }
                    if (str_contains($name, 'weekly')) { $termType = 'weekly'; break; }
                    if (str_contains($name, 'monthly')) { $termType = 'monthly'; break; }
                    if (str_contains($name, 'yearly')) { $termType = 'yearly'; break; }
                }
            }
        }

        return Lease::create([
            'parent_lease_id' => $oldLease?->id,
            'document_id' => in_array($status, ['New', 'Renew'])
                ? ($data['document_id'] ?? null)
                : $oldLease?->document_id,
            'is_current' => true,
            'leasable_type' => $context['leasable_type'],
            'leasable_id' => $context['leasable_id'],
            'tenant_id' => $context['tenant_id'],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'checked_out_at' => $checkedOutAt,
            'agreement_ended_at' => $agreementEndedAt,
            'term_type' => in_array($status, ['New', 'Renew'])
                ? $termType
                : $oldLease?->term_type,
            'status' => $status,
        ]);
    }

    /**
     * Create multiple dynamic charges and deposits from the form array.
     */
    protected function createLeaseCharges(Lease $lease, array $data): void
    {
        if (empty($data['charges']) || !is_array($data['charges'])) {
            return;
        }

        foreach ($data['charges'] as $index => $chargeData) {
            $amount = (float) ($chargeData['amount'] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            $feeType = FeeType::find($chargeData['fee_type_id']);
            $feeTypeName = $feeType->name;

            // Determine if it's refundable (deposit) or recurring/other fee
            $chargeType = match ($feeType?->category) {
                FeeTypeCategory::DEPOSIT => LeaseCharge::TYPE_REFUNDABLE,
                default => LeaseCharge::TYPE_RECURRING,
            };

            // Extract frequency from input, falling back to 'monthly' for recurring charges or 'one_time' for deposits
            $frequency = $chargeData['frequency'] ?? match (true) {
                $feeTypeName === 'Daily Rental' => 'daily',
                $feeTypeName === 'Weekly Rental' => 'weekly',
                $feeTypeName === 'Monthly Rental' => 'monthly',
                $feeTypeName === 'Yearly Rental' => 'yearly',
                $feeType?->category === FeeTypeCategory::MANAGEMENT => 'monthly',
                $feeType?->category === FeeTypeCategory::DEPOSIT => 'one_time',
                $feeType?->category === FeeTypeCategory::SERVICE => 'one_time',

                default => 'monthly',
            };

            if ($frequency === 'one_time') {
                $chargeType = $feeType?->category === FeeTypeCategory::DEPOSIT 
                    ? LeaseCharge::TYPE_REFUNDABLE 
                    : 'one_time';
            }

            // Set next_billing_date for recurring items starting from the lease start date (or today)
            $nextBillingDate = null;
            if ($chargeType === LeaseCharge::TYPE_RECURRING && $frequency !== 'one_time') {
                $startDate = $lease->start_date ? Carbon::parse($lease->start_date) : now();

                // If your initial invoice is generated upon lease creation, 
                // the *next* automated billing date should be one cycle after the start date:
                $nextBillingDate = match ($frequency) {
                    'daily'   => $startDate->copy()->addDay(),
                    'weekly'  => $startDate->copy()->addWeek(),
                    'monthly' => $startDate->copy()->addMonth(),
                    'yearly'  => $startDate->copy()->addYear(),
                    default   => $startDate->copy()->addMonth(),
                };
            }

            LeaseCharge::create([
                'lease_id' => $lease->id,
                'fee_type_id' => $chargeData['fee_type_id'],
                'description' => filled($chargeData['description'] ?? null) ? $chargeData['description'] : '',
                'amount' => (int) round($amount * 100), // Stored in cents
                'charge_type' => $chargeType,
                'frequency' => $frequency,
                'next_billing_date' => $nextBillingDate,
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
        }
    }

    protected function parseDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        return Carbon::parse($date)->format('Y-m-d');
    }

    /** Raw cents for optional preview formatting; never parse formatted agreement tokens. */
    public function getAgreementRentalBasis(Lease $lease): array
    {
        $charges = $lease->charges;
        $rent = $charges->filter(fn ($c) => $c->feeType?->category === FeeTypeCategory::RENT);
        $failure = fn ($message) => ['cents' => null, 'frequency' => null, 'error' => $message];
        if ($rent->isNotEmpty()) {
            $frequencies = $rent->pluck('frequency')->unique()->values();
            if ($frequencies->count() !== 1) {
                return $failure('Rental charges have multiple frequencies. A unique rental amount cannot be determined.');
            }
            $frequency = $frequencies[0];
            $amounts = $rent->map(fn ($charge) => $charge->getAttributes()['amount'] ?? null);
        } elseif ($charges->isEmpty()) {
            // Same legacy fallback as getAgreementVariables, with an explicit recorded frequency.
            $frequency = $lease->term_type;
            $amounts = collect([$lease->getAttributes()['rent_price'] ?? null]);
        } else {
            return $failure('Rental charge data is missing. No rental amount can be determined.');
        }
        if (!in_array($frequency, ['daily', 'monthly', 'yearly'], true)) {
            return $failure('Rental frequency is missing or unsupported. Expected daily, monthly or yearly.');
        }
        if ($amounts->contains(fn ($amount) => !is_scalar($amount) || !preg_match('/^\d+$/D', (string) $amount))) {
            return $failure('Rental amount is missing or invalid.');
        }
        $cents = $amounts->sum();
        if ($cents > 9007199254740991) {
            return $failure('Rental amount exceeds the supported exact amount range.');
        }
        return ['cents' => (int) $cents, 'frequency' => $frequency, 'error' => null];
    }

    /** Read-only variables shared by every live agreement preview and its PDF. */
    public function getAgreementVariables(Lease $lease): array
    {
        $leasable = $lease->leasable;
        $unit = $leasable instanceof Room ? $leasable->unit : ($leasable instanceof Unit ? $leasable : null);
        $property = $leasable instanceof Property ? $leasable : $unit?->property;
        // Match each asset's existing detail/list display through its model attribute.
        $servicePackage = match (true) {
            $leasable instanceof Property => $leasable->type,
            $leasable instanceof Unit => $leasable->type,
            $leasable instanceof Room => $leasable->room_type,
            default => null,
        };
        $servicePackage = filled($servicePackage) ? $servicePackage : 'Not recorded';
        // Compatibility default is the original Description in AntzCustomTemplate's TENANT agreement.
        // Existing hardcoded templates remain untouched by this additional variable.
        $servicePackageDescription = match (strtoupper(trim($servicePackage))) {
            'WIFI UNIT' => 'CUSTOMER SERVICE (9AM-5.30PM), INDAH WATER, FACILITY ALLOWANCE & HIGH SPEED WIFI',
            'WIFI Zone' => 'CUSTOMER SERVICE (9AM-5.30PM), INDAH WATER, FACILITY ALLOWANCE, CCTV, FACILITY ROOM & HIGH SPEED WIF',
            'STANDARD UNIT' => 'CUSTOMER SERVICE (9AM-5.30PM), INDAH WATER & FACILITY ALLOWANCE',
            'PREMIUM UNIT' => 'CUSTOMER SERVICE (9AM-5.30PM), INDAH WATER, FACILITY ALLOWANCE, CCTV, FACILITY ROOM & HIGH SPEED WIFI',
            default => 'CUSTOMER SERVICE (9AM-5.30PM), INDAH WATER,FACILITY ALLOWANCE, CCTV, FACILITY ROOM &HIGH SPEED WIFI',
        };
        $owner = $unit?->owner ?? $leasable?->owner;
        $charges = $lease->charges;
        $money = function ($selected, string $legacy) use ($lease, $charges): string {
            if ($selected->isNotEmpty()) {
                return number_format($selected->sum('amount') / 100, 2);
            }
            // Legacy columns are cents too. Never turn an absent value into zero.
            $raw = $lease->getAttributes()[$legacy] ?? null;
            return $charges->isEmpty() && $raw !== null ? number_format($raw / 100, 2) : 'Not recorded';
        };
        $rent = $charges->filter(fn ($c) => $c->feeType?->category === FeeTypeCategory::RENT);
        $deposit = $charges->filter(fn ($c) => $c->feeType?->category === FeeTypeCategory::DEPOSIT);
        $namedDeposit = fn ($name) => $deposit->filter(fn ($c) => strcasecmp(trim($c->description ?: $c->feeType?->name), $name) === 0);
        $rentValue = $money($rent, 'rent_price');
        // Do not invent a monthly equivalent for daily/weekly/yearly agreements.
        if ($rent->isNotEmpty() && $rent->contains(fn ($c) => $c->frequency !== 'monthly')) {
            $rentValue = $rent->map(fn ($c) => number_format($c->amount / 100, 2).' ('.$c->frequency.')')->implode('; ');
        }
        $security = $money($namedDeposit('Security Deposit'), 'security_deposit');
        $address = $this->formatAgreementAddress($unit?->unit_no, $property?->full_address);

        return [
            'status' => $lease->status ?? 'N/A',
            'tenant_name' => $lease->tenant?->user?->name ?? 'N/A',
            'tenant_ic' => $lease->tenant?->ic_number ?? $lease->tenant?->passport ?? 'N/A',
            'owner_name' => $owner?->name ?? 'N/A',
            'owner_ic' => $owner?->owner?->ic_number ?? 'N/A',
            'property_address' => $address ?: 'Not recorded',
            'unit_number' => $unit?->unit_no ?? ($leasable instanceof Property ? 'N/A (whole property)' : 'Not recorded'),
            'unit_no' => $unit?->unit_no ?? ($leasable instanceof Property ? 'N/A (whole property)' : 'Not recorded'),
            'property_type' => $lease->leasable_type_label,
            // These legacy agreement tokens denote the leased asset's Service Package.
            // Keep property_type above unchanged for its existing asset-kind usage.
            'leasable_type' => $servicePackage,
            'leasalable_type' => $servicePackage,
            'service_package' => $servicePackage,
            'service_package_description' => $servicePackageDescription,
            'property_name' => $lease->leasable_name,
            'rent_mode' => $lease->term_type ?? 'N/A',
            'rent_price' => $rentValue,
            'deposit_mode' => $lease->deposit_mode ?? 'N/A',
            'security_deposit' => $security,
            'deposit_amount' => $security,
            'utilities_deposit' => $money($namedDeposit('Utilities Deposit'), 'utilities_deposit'),
            'total_deposit' => $deposit->isNotEmpty() ? number_format($deposit->sum('amount') / 100, 2) : 'Not recorded',
            'start_date' => $lease->start_date?->format('d/m/Y') ?? 'N/A',
            'end_date' => $lease->end_date?->format('d/m/Y') ?? 'N/A',
            'check_out_date' => $lease->checked_out_at?->format('d/m/Y') ?? 'N/A',
            'end_agreement_date' => $lease->agreement_ended_at?->format('d/m/Y') ?? 'N/A',
        ];
    }

    private function formatAgreementAddress(?string $unitNumber, ?string $propertyAddress): string
    {
        $address = trim($propertyAddress ?? '');
        $number = trim($unitNumber ?? '');
        while (preg_match('/^\((.*)\)$/s', $number, $match)) {
            $number = trim($match[1]);
        }
        if ($number === '') {
            return $address;
        }

        // Remove only an exact leading unit component, never parentheses in a street name.
        $quoted = preg_quote($number, '/');
        $prefix = '/^(?:Unit\s+)?(?:\(\s*'.$quoted.'\s*\)|'.$quoted.')(?:\s*,\s*|$)/i';
        do {
            $address = preg_replace($prefix, '', $address, 1, $count);
        } while ($count > 0);

        return '('.$number.')'.($address !== '' ? ', '.$address : '');
    }

    public function cancelLease(Lease $lease, ?string $reason = null): void
    {
        // Guardrail: Check for outstanding unpaid invoices
        if ($lease->invoices()
            ->where('amount_balance', '>', 0)
            ->whereNotIn('status', ['void'])
            ->exists()) {
            throw new Exception('Cannot cancel lease with active outstanding unpaid invoices. Please settle or void them first.');
        }

        DB::transaction(function () use ($lease, $reason) {
            $lease->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => Auth::id(),
                'cancellation_reason' => $reason,
                'is_current' => 0,
            ]);

            // If there is a previous lease, reactivate it. Otherwise, mark the unit vacant.
            if ($lease->parent_lease_id) {
                Lease::where('id', $lease->parent_lease_id)->update(['is_current' => 1]);
            } else {
                if ($lease->leasable) {
                    $lease->leasable->update(['status' => 'vacant']);
                }
            }
        });
    }

    public function getLeaseFormPayload($user, Request $request): array
    {
        // 1. Properties
        $properties = $this->getAuthorizedProperties($user)
            ->select('properties.*')
            ->with(['owner.owner'])
            ->where('status', 'Vacant')
            ->get();

        // 2. Units
        $units = $this->getAuthorizedUnits($user)
            ->select('units.*')
            ->with(['property', 'owner.owner'])
            ->where('status', 'Vacant')
            ->get()
            ->each(function ($unit) {
                $propertyName = $unit->property?->name ?? 'N/A';
                $unit->display_label = "{$unit->unit_no} ({$propertyName})";
            });

        // 3. Rooms
        $rooms = $this->getAuthorizedRooms($user)
            ->select('rooms.*')
            ->with(['unit.owner.owner', 'owner.owner'])
            ->where('status', 'Vacant')
            ->get();

        // 4. Tenants
        $tenants = $this->applyOwnershipFilter(Tenants::query(), $user)->get();

        // 5. Fee Types
        $feeTypesQuery = FeeType::query()
            ->where('is_active', true)
            ->where(function ($query) use ($user) {
                $query->where('is_system', true);
                if ($user->role === 'ownerAdmin') {
                    $query->orWhere('user_id', $user->id);
                } elseif ($user->role === 'agentAdmin') {
                    $managedOwnerIds = Owners::where('agent_id', $user->id)->select('user_id');
                    $query->orWhere('user_id', $user->id)
                          ->orWhereIn('user_id', $managedOwnerIds);
                }
            });

        $feeTypes = $feeTypesQuery->orderBy('category')->orderBy('name')->get();
        $settingService = app(SettingService::class);
        $filteredFeeTypes = $settingService->filterActiveFeeTypes($feeTypes, $user->id);

        $rentFeeTypes = $filteredFeeTypes->where('category', FeeTypeCategory::RENT->value)->values();
        $serviceFeeTypes = $filteredFeeTypes->where('category', FeeTypeCategory::SERVICE->value)->values();
        $depositFeeTypes = $filteredFeeTypes->where('category', FeeTypeCategory::DEPOSIT->value)->values();
        $managementFeeTypes = $filteredFeeTypes->where('category', FeeTypeCategory::MANAGEMENT->value)->values();
        $itemFeeTypes = $filteredFeeTypes->where('category', FeeTypeCategory::ITEM->value)->values();

        // Base shared eager-loading and formatter closure for leases
        $leaseRelations = [
            'tenant.user',
            'charges.feeType',
            'charges.invoice',
            'invoices.items.feeType',
            'parentLease.charges.feeType',
            'refunds',
            'leasable' => function ($morphTo) {
                $morphTo->morphWith([
                    Room::class => ['unit.owner'],
                    Unit::class => ['owner'],
                    Property::class => ['owner'],
                ]);
            }
        ];

        $formatLeaseCallback = function ($lease) {
            $tenantName = $lease->tenant->user->name ?? 'Tenant';
            $status = $lease->status ?? '';
            $isPendingRenew = $lease->is_pending_renewal ? ' - Pending Renewal' : '';
            
            $propertyName = 'N/A';
            $typeLabel = 'N/A';
            $ownerId = null;

            if ($lease->leasable instanceof Property) {
                $propertyName = $lease->leasable->name;
                $typeLabel = 'Property';
                $ownerId = $lease->leasable->owner_id ?? $lease->leasable->owner->id ?? null;
            } elseif ($lease->leasable instanceof Unit) {
                $propertyName = $lease->leasable->unit_no;
                $typeLabel = 'Unit';
                $ownerId = $lease->leasable->owner_id ?? $lease->leasable->owner->id ?? null;
            } elseif ($lease->leasable instanceof Room) {
                $propertyName = $lease->leasable->room_no;
                $typeLabel = 'Room';
                $ownerId = $lease->leasable->unit->owner_id ?? $lease->leasable->unit->owner->id ?? null;
            }

            $dateRange = dateFormat($lease->start_date) . ' - ' . dateFormat($lease->end_date);
            $lease->computed_label = "{$tenantName}- {$propertyName} ({$typeLabel}) {$dateRange} ({$status}{$isPendingRenew})";
            $lease->owner_id = $ownerId; 
        };

        // 6. Filtered Existing Leases (For Table / Tabs)
        $status = $request->query('status');
        $leases = Lease::with($leaseRelations)
            ->where('is_current', true)
            ->when(
                $status === 'End',
                fn($q) => $q->where('status', 'Check Out'),
                fn($q) => $q->whereIn('status', ['New', 'Renew'])
            )
            ->when($user->role !== 'admin', fn($query) => $this->applyLeaseOwnershipFilter($query, $user))
            ->get()
            ->each($formatLeaseCallback);

        // 6b. ALL Current Leases (For Modals so checkout/renew selections never fail)
        $modalLeases = Lease::with($leaseRelations)
            ->where('is_current', true)
            ->when($user->role !== 'admin', fn($query) => $this->applyLeaseOwnershipFilter($query, $user))
            ->get()
            ->each($formatLeaseCallback);

        // 7. Lease Preview Data (Built from $modalLeases with deep deposit calculation logging)
        $leasePreviewData = $modalLeases->map(function ($lease) {
            $leasable = $this->getLeasableWithOwner($lease);
            
            // Helper to recursively collect charges across parent leases
            $collectFullHistory = function ($l, &$accumulator = []) use (&$collectFullHistory) {
                if (!$l) return $accumulator;
                foreach ($l->charges ?? [] as $charge) {
                    $accumulator[] = $charge;
                }
                $parent = $l->parentLease ?? $l->parent_lease;
                if ($parent) {
                    $collectFullHistory($parent, $accumulator);
                }
                return $accumulator;
            };

            $allHistoryCharges = $collectFullHistory($lease);

            // 1. Calculate Total Deposit Collected (where category is deposit and invoice is paid)
            $totalDepositCents = collect($allHistoryCharges)->filter(function ($charge) {
                $category = $charge->feeType?->category ?? null;
                $isDeposit = ($category === FeeTypeCategory::DEPOSIT || $category === 'deposit');
                
                if (!$isDeposit) {
                    return false;
                }

                // Check invoice status from direct relation or invoice items bridge
                $linkedInvoice = $charge->invoice ?? \App\Models\Invoice::whereHas('items', function ($q) use ($charge) {
                    $q->where('lease_charge_id', $charge->id);
                })->first();

                $invoiceStatus = strtolower($linkedInvoice?->status ?? '');
                $chargeStatus = strtolower($charge->status ?? '');

                $isPaid = ($invoiceStatus === 'paid' || $chargeStatus === 'paid' || $charge->is_paid === true);

                return $isPaid;
            })->sum('amount');

            // 2. Calculate Auto-Deductions (Unpaid / non-void invoices in the lease history)
            $unpaidInvoices = $lease->invoices ? $lease->invoices->filter(fn($inv) => strtolower($inv->status) !== 'paid' && strtolower($inv->status) !== 'void') : collect();
            
            $formattedUnpaidInvoices = $unpaidInvoices->map(function ($inv) {
                $balanceCents = $inv->amount_balance ?? $inv->total_amount ?? 0;
                $feeNames = $inv->items->map(fn($item) => $item->feeType?->name ?? $item->description)->filter()->unique()->implode(', ');
                
                return [
                    'id' => $inv->id,
                    'invoice_no' => $inv->invoice_no ?? $inv->id,
                    'description' => $feeNames ?: ($inv->description ?? 'General Charge'),
                    'balance_cents' => $balanceCents,
                    'balance_formatted' => number_format($balanceCents / 100, 2),
                ];
            })->values();

            $autoDeductionTotalCents = $unpaidInvoices->sum(fn($inv) => $inv->amount_balance ?? $inv->total_amount ?? 0);

            $cumulativeSecurity = 0;
            $cumulativeUtilities = 0;
            $current = $lease;

            while ($current) {
                $cumulativeSecurity += $current->security_deposit ?? 0;
                $cumulativeUtilities += $current->utilities_deposit ?? 0;
                $current = $current->parent_lease_id ? Lease::find($current->parent_lease_id) : null;
            }

            // 🌟 Retrieve the latest active refund for this lease
            $activeRefund = $lease->refunds()->with('items')->latest()->first();

            // 🔍 Log the refund data to the 'testing' channel
            //Log::channel('testing')->info('Lease Preview Refund Data Debug:', [
            //    'lease_id' => $lease->id,
            //    'refund_id' => $activeRefund?->id,
            //    'status' => $activeRefund?->status,
            //    'items_count' => $activeRefund?->items?->count() ?? 0,
            //    'items' => $activeRefund?->items?->toArray() ?? [],
            //    'full_refund' => $activeRefund ? $activeRefund->toArray() : null,
            //]);

            return array_merge(
                $lease->toArray(),
                [
                    'leasable_name' => $this->getLeasableName($leasable),
                    'leasable_address' => $this->getLeasableAddress($leasable),
                    'owner_data' => $this->getOwnerData($leasable),
                    'cumulative_security' => $cumulativeSecurity,
                    'cumulative_utilities' => $cumulativeUtilities,
                    'charges' => $lease->charges,
                    'invoices' => $lease->invoices,
                    'total_deposit_collected_cents' => $totalDepositCents,
                    'total_deposit_collected_formatted' => number_format($totalDepositCents / 100, 2),
                    'unpaid_invoices' => $formattedUnpaidInvoices,
                    'auto_deduction_total_cents' => $autoDeductionTotalCents,
                    'auto_deduction_total_formatted' => number_format($autoDeductionTotalCents / 100, 2),
                    'active_refund' => $activeRefund,
                ]
            );
        });

        // 8. Templates
        $templates = $this->applyOwnershipFilter(
            DocumentTemplate::query()->where('category', 'agreement')->where('status', 'active'),
            $user,
            'user_id'
        )->get();

        $statuses = ['New', 'Renew', 'Check Out', 'End'];

        return compact(
            'properties',
            'units',
            'rooms',
            'tenants',
            'leases',
            'modalLeases', // <--- Pass this to your payload array
            'leasePreviewData',
            'templates',
            'rentFeeTypes',
            'serviceFeeTypes',
            'depositFeeTypes',
            'managementFeeTypes',
            'itemFeeTypes',
            'statuses'
        );
    }

    private function getLeasableWithOwner($lease)
    {
        if ($lease->leasable_type === 'App\Models\Property' || strpos($lease->leasable_type, 'Property') !== false) {
            return Property::with('owner')->find($lease->leasable_id);
        } elseif ($lease->leasable_type === 'App\Models\Unit' || strpos($lease->leasable_type, 'Unit') !== false) {
            return Unit::with('owner')->find($lease->leasable_id);
        } elseif ($lease->leasable_type === 'App\Models\Room' || strpos($lease->leasable_type, 'Room') !== false) {
            return Room::with('unit.owner')->find($lease->leasable_id);
        }
        return null;
    }

    private function getLeasableName($leasable)
    {
        if ($leasable) {
            if ($leasable instanceof Property) {
                return $leasable->name;
            } elseif ($leasable instanceof Unit) {
                return $leasable->unit_no;
            } elseif ($leasable instanceof Room) {
                return $leasable->room_no;
            }
        }
        return '';
    }

    private function getLeasableAddress($leasable)
    {
        if ($leasable) {
            if ($leasable instanceof Property) {
                return $leasable->full_address ?? '';
            } elseif ($leasable instanceof Unit) {
                return $leasable->full_address ?? '';
            } elseif ($leasable instanceof Room) {
                return $leasable->full_address ?? '';
            }
        }
        return '';
    }

    private function getOwnerData($leasable)
    {
        if ($leasable) {
            if ($leasable instanceof Property && $leasable->owner) {
                return [
                    'id' => $leasable->owner->id ?? '',
                    'name' => $leasable->owner->name ?? '',
                    'ic_number' => $leasable->owner->ic_number ?? '',
                ];
            } elseif ($leasable instanceof Unit && $leasable->owner) {
                return [
                    'id' => $leasable->owner->id ?? '',
                    'name' => $leasable->owner->name ?? '',
                    'ic_number' => $leasable->owner->ic_number ?? '',
                ];
            } elseif ($leasable instanceof Room && $leasable->unit && $leasable->unit->owner) {
                return [
                    'id' => $leasable->unit->owner->id ?? '',
                    'name' => $leasable->unit->owner->name ?? '',
                    'ic_number' => $leasable->unit->owner->ic_number ?? '',
                ];
            }
        }
        return ['id' => '', 'name' => '', 'ic_number' => ''];
    }

}
