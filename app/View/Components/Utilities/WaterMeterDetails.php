<?php

namespace App\View\Components\Utilities;

use App\Models\User;
use App\Models\UtilityService;
use App\Services\UtilitySetupService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\Component;
use Illuminate\View\View;

class WaterMeterDetails extends Component
{
    public ?UtilityService $meter = null;

    private bool $visible = false;

    public function __construct(Model $location)
    {
        if (! Gate::any(['super-admin', 'utilities.tab']) || Gate::any(['is-tenant', 'is-owner'])
            || in_array(Auth::user()?->role, [User::ROLE_TENANT, User::ROLE_OWNER], true)) {
            return;
        }

        $manager = app(UtilitySetupService::class)->managerFor($location);
        if (! $manager || (Gate::denies('super-admin') && $manager->id !== get_effective_user()?->id)) {
            return;
        }

        $this->visible = true;
        $this->meter = $location->utilityServices()->forManagement($manager->id)
            ->where('type', 'water')->where('billing_method', 'metered')
            ->with('latestRecord')->first();
    }

    public function shouldRender(): bool
    {
        return $this->visible;
    }

    public function render(): View
    {
        return view('components.utilities.water-meter-details');
    }
}
