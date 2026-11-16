<?php
namespace App\Http\Controllers;

use App\Models\{UtilityTariff, UtilityService};
use App\Services\{UtilityBillingAccess, UtilityTariffManagementService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UtilityTariffController extends Controller
{
    public function index(Request $request, UtilityBillingAccess $access)
    {
        if (!(Gate::any(['super-admin', 'utilities.tab']) && Gate::any(['super-admin', 'utilities.tariff tab'])) && !Gate::any(['settings.utility tab', 'settings.edit utility meter prices'])) return view('errors.403');
        $manager = $access->manager($request, true);
        return redirect()->route('admin.settings.index', ['tab' => 'utility',
            'management_user_id' => $manager->role === 'admin' ? null : $manager->id]);
    }

    public function store(Request $request, UtilityBillingAccess $access, UtilityTariffManagementService $tariffs)
    {
        if (!(Gate::any(['super-admin', 'utilities.tab']) && Gate::any(['super-admin', 'utilities.edit tariffs'])) && !Gate::allows('settings.edit utility meter prices')) return redirect()->back()->with('error', 'You have no permission.');
        $manager = $access->manager($request, true);
        $data = $request->validate(['tariff' => ['required', 'array'], 'official' => ['nullable', 'boolean']]);
        if ($request->boolean('official') && Gate::denies('super-admin')) return redirect()->back()->with('error', 'You have no permission.');
        $version = $tariffs->save($manager, $data['tariff'], $request->boolean('official'));
        return redirect()->route('admin.settings.index', ['tab' => 'utility', 'management_user_id' => $manager->role === 'admin' ? null : $manager->id])
            ->with('success', 'Tariff version '.$version->version.' saved. Existing charge snapshots and invoices were preserved.');
    }

    public function toggle(Request $request, UtilityTariff $tariff, UtilityBillingAccess $access, UtilityTariffManagementService $tariffs)
    {
        if (!(Gate::any(['super-admin', 'utilities.tab']) && Gate::any(['super-admin', 'utilities.edit tariffs'])) && !Gate::allows('settings.edit utility meter prices')) return redirect()->back()->with('error', 'You have no permission.');
        $manager = $access->manager($request, true);
        if ($tariff->kind === 'official' && Gate::denies('super-admin')) return redirect()->back()->with('error', 'You have no permission.');
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $tariffs->toggle($manager, $tariff, (bool) $data['is_active']);
        return back()->with('success', 'Tariff availability updated. Historical versions were retained.');
    }

    public function assign(Request $request, UtilityTariff $tariff, UtilityBillingAccess $access, UtilityTariffManagementService $tariffs)
    {
        if (!(Gate::any(['super-admin', 'utilities.tab']) && Gate::any(['super-admin', 'utilities.edit tariffs'])) && !Gate::allows('settings.edit utility meter prices')) return redirect()->back()->with('error', 'You have no permission.');
        $manager = $access->manager($request);
        if (!$manager) return back()->with('error', 'Select a management account first.');
        $data = $request->validate(['service_ids' => ['required', 'json'], 'property_ids' => ['nullable', 'json'],
            'effective_from' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $ids = json_decode($data['service_ids'], true); $properties = json_decode($data['property_ids'] ?? '[]', true);
        validator(['ids' => $ids, 'properties' => $properties], ['ids' => ['array', 'max:2000'],
            'ids.*' => ['ulid'], 'properties' => ['array', 'max:2000'], 'properties.*' => ['ulid']])->validate();
        if ($properties) {
            $owned = app(\App\Services\UtilitySetupService::class)->managedLocations(\App\Models\Property::class, $manager)->modelKeys();
            abort_if(array_diff($properties, $owned), 403);
            foreach (UtilityService::forManagement($manager->id)->where('type', 'water')->with('location')->get() as $service) {
                $location = $service->location;
                $propertyId = match (true) {
                    $location instanceof \App\Models\Property => $location->id,
                    $location instanceof \App\Models\Unit => $location->property_id,
                    $location instanceof \App\Models\Room => $location->unit?->property_id,
                    default => null,
                };
                if (in_array($propertyId, $properties, true)) $ids[] = $service->id;
            }
        }
        $tariffs->assign($manager, $tariff, array_values(array_unique($ids)), $data['effective_from'], $data['reason']);
        return back()->with('success', 'Dated tariff assignments saved. Existing invoices were not recalculated.');
    }
}
