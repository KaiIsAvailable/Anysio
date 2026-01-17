<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\Room;
use App\Models\Tenants;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeaseController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $query = Lease::with([
            'room.owner.user',
            'tenant.user',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('status', 'like', '%' . $search . '%')
                    ->orWhereHas('room', function ($rq) use ($search) {
                        $rq->where('room_no', 'like', '%' . $search . '%')
                            ->orWhere('room_type', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('tenant', function ($tq) use ($search) {
                        $tq->where('ic_number', 'like', '%' . $search . '%')
                            ->orWhereHas('user', function ($uq) use ($search) {
                                $uq->where('name', 'like', '%' . $search . '%')
                                    ->orWhere('email', 'like', '%' . $search . '%');
                            });
                    });
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $leases = $query->orderBy('created_at', 'desc')
            ->paginate(10)
            ->appends($request->query());

        $groups = $leases->getCollection()
            ->groupBy(function (Lease $lease) {
                return $lease->tenant_id . '|' . $lease->room_id;
            });

        $statusOptions = ['New', 'Renew', 'Check out', 'End'];

        return view('adminSide.leases.index', compact('leases', 'groups', 'statusOptions'));
    }

    public function create(Request $request)
    {
        $roomId = $request->query('room_id');
        $tenantId = $request->query('tenant_id');
        $forceRenew = $request->boolean('renew');

        $rooms = Room::with('owner.user')
            ->orderBy('room_no')
            ->get();

        $statuses = ['New', 'Renew', 'Check out', 'End'];

        $selectedRoom = $roomId ? Room::with('owner.user')->find($roomId) : null;
        $selectedTenant = $tenantId ? Tenants::with('user')->find($tenantId) : null;
        $latestStatus = null;
        if ($forceRenew && $roomId && $tenantId) {
            $latestStatus = Lease::where('room_id', $roomId)
                ->where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc')
                ->value('status');
        }

        return view('adminSide.leases.create', compact(
            'rooms',
            'statuses',
            'selectedRoom',
            'selectedTenant',
            'forceRenew',
            'latestStatus'
        ));
    }

    public function store(Request $request)
    {
        $statusInput = $request->input('status');
        $isFinal = in_array($statusInput, ['Check out', 'End'], true);
        $utilitiesRule = $isFinal ? ['nullable', 'array'] : ['required', 'array'];
        $utilityFieldRule = $isFinal ? ['nullable', 'numeric', 'min:0'] : ['required', 'numeric', 'min:0'];

        $dateRule = $isFinal ? ['nullable', 'date'] : ['required', 'date', 'after_or_equal:today'];
        $endDateRule = $isFinal ? ['nullable', 'date'] : ['required', 'date', 'after_or_equal:start_date'];
        $moneyRule = $isFinal ? ['nullable', 'numeric', 'min:0'] : ['required', 'numeric', 'min:0'];

        $payload = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'tenant_id' => ['required', 'exists:tenants,id'],
            'start_date' => $dateRule,
            'end_date' => $endDateRule,
            'monthly_rent' => $moneyRule,
            'security_deposit' => $moneyRule,
            'utilities_deposit' => $moneyRule,
            'status' => ['nullable', Rule::in(['New', 'Renew', 'Check out', 'End'])],
            'renew' => ['nullable', 'boolean'],
            'utilities' => $utilitiesRule,
            'utilities.water.prev' => $utilityFieldRule,
            'utilities.water.curr' => $utilityFieldRule,
            'utilities.electric.prev' => $utilityFieldRule,
            'utilities.electric.curr' => $utilityFieldRule,
        ]);

        $roomId = $payload['room_id'];
        $tenantId = $payload['tenant_id'];

        $hasActive = Lease::where('room_id', $roomId)
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'End')
            ->exists();

        $forceRenew = (bool) ($payload['renew'] ?? false);
        $latestStatus = Lease::where('room_id', $roomId)
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->value('status');
        if ($hasActive && !$forceRenew) {
            return back()
                ->withErrors(['tenant_id' => 'This tenant already has an active lease for this room.'])
                ->withInput();
        }

        $hasPrevious = Lease::where('room_id', $roomId)
            ->where('tenant_id', $tenantId)
            ->exists();

        if ($forceRenew) {
            $status = $payload['status'] ?? 'Renew';
            if ($status === 'New') {
                return back()
                    ->withErrors(['status' => 'Status cannot be New for renew.'])
                    ->withInput();
            }
            if (strtolower((string) $latestStatus) === 'check out' && $status !== 'End') {
                return back()
                    ->withErrors(['status' => 'Status must be End after checkout.'])
                    ->withInput();
            }
        } else {
            $status = $hasPrevious ? 'Renew' : 'New';
        }

        if (in_array($status, ['Check out', 'End'], true)) {
            $today = now()->toDateString();
            $payload['start_date'] = $today;
            $payload['end_date'] = $today;
            $payload['monthly_rent'] = 0;
            $payload['security_deposit'] = 0;
            $payload['utilities_deposit'] = 0;
            $payload['utilities'] = [
                'water' => ['prev' => 0, 'curr' => 0],
                'electric' => ['prev' => 0, 'curr' => 0],
            ];
        }

        $waterPrev = $this->toCents($payload['utilities']['water']['prev']);
        $waterCurr = $this->toCents($payload['utilities']['water']['curr']);
        $electricPrev = $this->toCents($payload['utilities']['electric']['prev']);
        $electricCurr = $this->toCents($payload['utilities']['electric']['curr']);

        if ($waterCurr < $waterPrev) {
            return back()
                ->withErrors(['utilities.water.curr' => 'Water current amount must be greater than or equal to previous.'])
                ->withInput();
        }

        if ($electricCurr < $electricPrev) {
            return back()
                ->withErrors(['utilities.electric.curr' => 'Electric current amount must be greater than or equal to previous.'])
                ->withInput();
        }

        DB::transaction(function () use ($payload, $status, $waterPrev, $waterCurr, $electricPrev, $electricCurr) {
            $lease = Lease::create([
                'room_id' => $payload['room_id'],
                'tenant_id' => $payload['tenant_id'],
                'start_date' => $payload['start_date'],
                'end_date' => $payload['end_date'],
                'monthly_rent' => $this->toCents($payload['monthly_rent']),
                'security_deposit' => $this->toCents($payload['security_deposit']),
                'utilities_depost' => $this->toCents($payload['utilities_deposit']),
                'status' => $status,
            ]);

            Utility::create([
                'lease_id' => $lease->id,
                'type' => 'water',
                'prev_reading' => $waterPrev,
                'curr_reading' => $waterCurr,
                'amount' => $waterCurr - $waterPrev,
            ]);

            Utility::create([
                'lease_id' => $lease->id,
                'type' => 'electric',
                'prev_reading' => $electricPrev,
                'curr_reading' => $electricCurr,
                'amount' => $electricCurr - $electricPrev,
            ]);
        });

        return redirect()
            ->route('admin.leases.index')
            ->with('success', 'Lease created successfully.');
    }

    public function show(Lease $lease)
    {
        $lease->load([
            'room.owner.user',
            'room.assets',
            'tenant.user',
            'utilities',
        ]);

        return view('adminSide.leases.show', compact('lease'));
    }

    public function edit()
    {
        abort(403);
    }

    public function update()
    {
        abort(403);
    }

    public function destroy()
    {
        abort(403);
    }

    public function tenantSearch(Request $request)
    {
        $ic = trim((string) $request->query('ic', ''));
        if ($ic === '') {
            return response()->json([]);
        }

        $tenants = Tenants::with('user')
            ->where('ic_number', 'like', '%' . $ic . '%')
            ->orderBy('ic_number')
            ->limit(10)
            ->get()
            ->map(function (Tenants $tenant) {
                return [
                    'id' => $tenant->id,
                    'ic_number' => $tenant->ic_number,
                    'name' => $tenant->user?->name,
                    'email' => $tenant->user?->email,
                ];
            })
            ->values();

        return response()->json($tenants);
    }

    private function toCents($value): int
    {
        $sanitized = preg_replace('/[^0-9.]/', '', (string) $value);
        if ($sanitized === '') {
            return 0;
        }

        return (int) round(((float) $sanitized) * 100);
    }
}
