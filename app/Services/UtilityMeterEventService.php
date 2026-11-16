<?php
namespace App\Services;

use App\Models\{User, UtilityService, UtilityMeterEvent};
use Illuminate\Support\Facades\{Auth, DB, Gate, Validator};
use Illuminate\Validation\ValidationException;

class UtilityMeterEventService
{
    public function create(User $manager, UtilityService $service, array $input): UtilityMeterEvent
    {
        app(UtilityBillingAccess::class)->meter($manager, $service);
        abort_unless(Gate::allows('super-admin') || (get_effective_user()?->id === $manager->id
            && !in_array(Auth::user()?->role, [User::ROLE_TENANT, 'owner'])), 403);
        abort_unless(Gate::any(['super-admin', 'utilities.manage meters']), 403);
        $data = Validator::make($input, [
            'type' => ['required', 'in:replacement,reset,rollover,correction'],
            'event_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'old_closing' => ['required_unless:type,rollover', 'nullable', 'numeric', 'between:0,999999999999.999', 'decimal:0,3'],
            'new_opening' => ['required_unless:type,rollover', 'nullable', 'numeric', 'between:0,999999999999.999', 'decimal:0,3'],
            'capacity' => ['required_if:type,rollover', 'nullable', 'numeric', 'gt:0', 'max:999999999999.999', 'decimal:0,3'],
            'new_meter_number' => ['nullable', 'string', 'max:255'], 'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ])->validate();
        return DB::transaction(function () use ($manager, $service, $data) {
            User::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            $service = UtilityService::whereKey($service->id)->lockForUpdate()->firstOrFail();
            if ($service->meterEvents()->whereNull('consumed_by_utility_id')->exists()) {
                throw ValidationException::withMessages(['meter_event' => 'Complete a reading for the existing pending event before recording another.']);
            }
            if ($service->records()->whereDate('curr_reading_date', '>', $data['event_date'])->whereNotNull('curr_reading')->exists()) {
                throw ValidationException::withMessages(['meter_event' => 'Later readings already exist. Use the charge correction workflow for a historical correction.']);
            }
            $event = $service->meterEvents()->create($data + ['management_user_id' => $manager->id,
                'created_by' => Auth::id(), 'old_meter_number' => $service->meter_number]);
            if (!empty($data['new_meter_number'])) $service->update(['meter_number' => $data['new_meter_number']]);
            return $event;
        });
    }
}
