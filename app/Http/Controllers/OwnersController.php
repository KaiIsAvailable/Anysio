<?php

namespace App\Http\Controllers;

use App\Models\{Owners, User, Lease, Room, Unit, Property, Tenants, Payment};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;
use App\Services\{BankAccountService, CompanyService, OwnerIdentityValidator};
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use App\WorldCountries;
use App\Http\Requests\Owner\{OwnerDetailsRequest, StoreOwnerRequest, UpdateOwnerRequest};

class OwnersController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::denies('super-admin') && Gate::denies('owner.tab')) {
            return view('errors.403');
        }

        $userId = get_effective_user();
        $query = Owners::with('user.companies');

        if (!Gate::allows('super-admin')) {
            if ($userId) {
                $query->where('agent_id', $userId->id);
            } else {
                // 如果该用户在 owners 表里竟然没有记录，为了安全，让他什么都搜不到
                $query->whereRaw('1 = 0');
            }
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('users.name', 'like', '%' . $search . '%')
                        ->orWhere('users.email', 'like', '%' . $search . '%');
                })
                    ->orWhereHas('user.companies', fn ($company) => $company->where('name', 'like', '%'.$search.'%'))
                    ->orWhere('owners.phone', 'like', '%' . $search . '%')
                    ->orWhere('owners.ic_number', 'like', '%' . $search . '%')
                    ->orWhere('owners.gender', 'like', '%' . $search . '%');
            });
        }

        // Sorting
        $sort = $request->get('sort');
        if ($sort) {
            if (str_starts_with($sort, 'n_')) {
                $direction = str_ends_with($sort, '_asc') ? 'asc' : 'desc';
                $query->join('users', 'owners.user_id', '=', 'users.id')
                    ->select('owners.*')
                    ->orderBy('users.name', $direction);
            } elseif (str_starts_with($sort, 'c_')) {
                $direction = str_ends_with($sort, '_asc') ? 'asc' : 'desc';
                $query->orderBy(\App\Models\Company::select('name')
                    ->join('company_user', 'companies.id', '=', 'company_user.company_id')
                    ->whereColumn('company_user.user_id', 'owners.user_id')
                    ->orderBy('companies.id')->limit(1), $direction);
            } elseif (str_starts_with($sort, 'jd_')) {
                $direction = str_ends_with($sort, '_asc') ? 'asc' : 'desc';
                $query->orderBy('owners.created_at', $direction);
            } else {
                $query->orderBy('owners.created_at', 'asc');
            }
        } else {
            $query->orderBy('owners.created_at', 'asc');
        }

        $owners = $query->paginate(10)->withQueryString()->onEachSide(1);
        return view('adminSide.owners.index', compact('owners'));
    }

    public function create()
    {
        if (Gate::denies('super-admin') && Gate::denies('owner.create')) {
            return view('errors.403');
        }

        $users = User::where('role', 'owner')->whereDoesntHave('owner')->get();
        $worldCountries = WorldCountries::worldCountries();
        return view('adminSide.owners.create', compact('users', 'worldCountries'));
    }

    public function store(StoreOwnerRequest $request)
    {
        if (Gate::denies('super-admin') && Gate::denies('owner.create')) {
            return back()->with('error', 'You have no permission.');
        }

        $data = $request->validated();
        $uploads = [];

        try {
            DB::transaction(function () use ($request, $data, &$uploads) {
                $managerId = get_effective_user()?->id ?? Auth::id();
                $identity = app(OwnerIdentityValidator::class);
                $identity->lockAccounts([$managerId]);
                Validator::make($data, $identity->rules(null, $managerId))->validate();
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['random_email'] ? Str::random(10).'@example.com' : $data['email'],
                    'password' => Hash::make(Str::random(20)),
                    'role' => 'owner',
                ]);

                Owners::create(array_merge($request->safe()->only([
                    'ic_number', 'phone', 'gender', 'address', 'postcode', 'city', 'state',
                ]), [
                    'user_id' => $user->id,
                    'agent_id' => $managerId,
                ]));

                $this->saveOptionalDetails($request, $user, $uploads);
            });
        } catch (\Throwable $exception) {
            $this->removeUploadedFiles($uploads);
            if ($exception instanceof ValidationException) {
                throw $exception;
            }
            report($exception);
            return back()->withInput($request->except(['account_number', 'company_logo', 'ssm_cert_path']))
                ->with('error', 'Unable to create owner. Please try again.');
        }

        return redirect()->route('admin.owners.index')->with('success', 'Owner created successfully.');
    }

    public function edit(Owners $owner)
    {
        if ((Gate::denies('super-admin') && Gate::denies('owner.edit')) || !$this->canAccessOwner($owner)) {
            return view('errors.403');
        }

        $owner->load('user.companies');
        $company = $owner->user?->companies->first();
        $bankDetail = $owner->user
            ? app(BankAccountService::class)->getDefaultForUser($owner->user) : null;
        $worldCountries = WorldCountries::worldCountries();

        return view('adminSide.owners.edit', compact('owner', 'worldCountries', 'company', 'bankDetail'));
    }

    public function update(UpdateOwnerRequest $request, Owners $owner)
    {
        if ((Gate::denies('super-admin') && Gate::denies('owner.edit')) || !$this->canAccessOwner($owner)) {
            return back()->with('error', 'You have no permission.');
        }

        if (!$owner->user) {
            return back()->with('error', 'The owner account is unavailable.');
        }

        $uploads = [];
        try {
            DB::transaction(function () use ($request, $owner, &$uploads) {
                $identity = app(OwnerIdentityValidator::class);
                $identity->lockAccounts([$owner->agent_id]);
                Validator::make($request->validated(), $identity->rules($owner, $owner->agent_id))->validate();
                $user = User::whereKey($owner->user_id)->lockForUpdate()->firstOrFail();
                $user->update(array_merge($request->safe()->only(['name', 'email']), [
                    'email_verified_at' => $user->email === $request->validated('email') ? $user->email_verified_at : null,
                ]));
                $owner->update($request->safe()->only([
                    'ic_number', 'phone', 'gender', 'address', 'postcode', 'city', 'state',
                ]));
                $this->saveOptionalDetails($request, $user, $uploads);
            });
        } catch (\Throwable $exception) {
            $this->removeUploadedFiles($uploads);
            if ($exception instanceof ValidationException) {
                throw $exception;
            }
            report($exception);
            return back()->withInput($request->except(['account_number', 'company_logo', 'ssm_cert_path']))
                ->with('error', 'Unable to update owner. Please try again.');
        }

        return redirect()->route('admin.owners.index')->with('success', 'Owner updated successfully.');
    }

    private function canAccessOwner(Owners $owner): bool
    {
        return Gate::allows('super-admin')
            || ($owner->agent_id !== null && $owner->agent_id === get_effective_user()?->id);
    }

    private function saveOptionalDetails(OwnerDetailsRequest $request, User $user, array &$uploads): void
    {
        if ($request->filled('company_name')) {
            app(CompanyService::class)->saveProfileForUser(
                $user,
                $request->safe()->only(['company_name', 'ssm_old_number', 'ssm_new_number', 'company_address', 'company_phone', 'company_email']),
                $request->file('ssm_cert_path'),
                $request->file('company_logo'),
                function (string $disk, string $path) use (&$uploads): void {
                    $uploads[] = [$disk, $path];
                },
                'owner_ssm',
            );
        }

        if ($request->filled('bank_name')) {
            app(BankAccountService::class)->saveForUser($user, $request->safe()->only([
                'bank_name', 'bank_code', 'account_holder_name', 'account_number',
            ]));
        }
    }

    private function removeUploadedFiles(array $uploads): void
    {
        foreach ($uploads as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }
    }

    public function destroy(Owners $owner)
    {
        if ((Gate::denies('super-admin') && Gate::denies('owner.delete')) || !$this->canAccessOwner($owner)) {
            return redirect()->back()->with('error', 'You have no permission.');
        }


        if ($owner->user) {
            $owner->user->update([
                'status' => 'inactive'
            ]);
        }

        return redirect()->route('admin.owners.index')->with('success', 'Owner marked as inactive successfully.');
    }

    public function restore($id)
    {
        if (Gate::denies('super-admin') && Gate::denies('owner.delete')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        // Find the owner including inactive ones (just in case your global scopes filter them out)
        $owner = Owners::with('user')->findOrFail($id);
        if (!$this->canAccessOwner($owner)) {
            return back()->with('error', 'You have no permission.');
        }

        if ($owner->user) {
            $owner->user->update([
                'status' => 'active' // Change back to active
            ]);
        }

        return redirect()->route('admin.owners.index')->with('success', 'Owner restored/activated successfully.');
    }

    public function show(Owners $owner)
    {
        if ((Gate::denies('super-admin') && Gate::denies('owner.show')) || !$this->canAccessOwner($owner)) {
            return view('errors.403');
        }

        // Owner profile
        $owner->load('user.companies');
        $company = $owner->user?->companies->first();
        $bankDetail = $owner->user ? app(BankAccountService::class)->getDefaultForUser($owner->user) : null;

        // Property / Unit 的 owner_id 都储存 users.id
        $ownerUserId = $owner->user_id;

        $properties = Property::withCount('units')
            ->where('owner_id', $ownerUserId)
            ->orderBy('name')
            ->get();

        $units = Unit::with('property')
            ->withCount('rooms')
            ->where('owner_id', $ownerUserId)
            ->orderBy('unit_no')
            ->get();

        // 3. Room 本身没有 owner_id
        //    所以通过 Unit 的 owner_id 判断 Room 属于哪个 Owner
        $rooms = Room::with('unit.property')
            ->whereHas('unit', function ($query) use ($ownerUserId) {
                $query->where('owner_id', $ownerUserId);
            })
            ->orderBy('room_no')
            ->get();

        return view('adminSide.owners.details', compact(
            'owner',
            'company',
            'bankDetail',
            'properties',
            'units',
            'rooms'
        ));
    }

    public function companyCertificate(Owners $owner)
    {
        if ((Gate::denies('super-admin') && Gate::denies('owner.show') && Gate::denies('owner.edit')) || !$this->canAccessOwner($owner)) {
            return view('errors.403');
        }

        $path = $owner->user?->companies()->first()?->ssm_cert_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return app(\App\Services\FileService::class)->getStreamResponse($path);
    }

    public function dashboard()
    {
        $user = Auth::user();

        // 先找到当前登录用户对应的 Owner 业务记录
        $ownerProfile = Owners::where('user_id', $user->id)->first();

        // 如果该用户甚至不是一个登记的业主，直接返回 0
        if (!$ownerProfile) {
            return view('adminSide.owners.dashboard', [
                'ownersCount' => 0,
                'tenantsCount' => 0,
                'roomsCount' => 0,
                'leasesCount' => 0,
                'roomStatusStats' => collect(),
                'payments' => collect()
            ]);
        }

        $owner_id = $ownerProfile->id; // 获取 Owners 表的 ULID

        // 1. 统计租客 (通过房间的 owner_id 匹配)
        $tenantsCount = Tenants::whereHas('leases.room', function ($query) use ($owner_id) {
            $query->where('owner_id', $owner_id);
        })->count();

        // 2. 统计房间
        $roomsCount = Room::whereHas('unit', function ($query) use ($owner_id) {
            $query->where('owner_id', $owner_id);
        })->count();

        // 3. 统计租约
        $leasesCount = Lease::where(function ($query) use ($owner_id) {
            // 1. 如果租的是 Room，通过 Room -> Unit -> Owner 找
            $query->whereHasMorph('leasable', [Room::class], function ($q) use ($owner_id) {
                $q->whereHas('unit', function ($sq) use ($owner_id) {
                    $sq->where('owner_id', $owner_id);
                });
            })
                // 2. 或者：如果租的是 Unit，通过 Unit -> Owner 找
                ->orWhereHasMorph('leasable', [Unit::class], function ($q) use ($owner_id) {
                    $q->where('owner_id', $owner_id);
                })
                // 3. 或者：如果租的是 Property，通过 Property -> Owner 找
                ->orWhereHasMorph('leasable', [Property::class], function ($q) use ($owner_id) {
                    $q->where('owner_id', $owner_id);
                });
        })
            ->where('status', 'active') // 记得只算 active 的，这才是占用名额的
            ->count();

        // 4. 饼图：房间状态
        $roomStatusStats = Room::whereHas('unit', function ($query) use ($owner_id) {
            $query->where('owner_id', $owner_id);
        })
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // 5. 支付动态
        $payments = Payment::with('tenant')
            ->whereHas('tenant.leases.room', function ($query) use ($owner_id) {
                $query->where('owner_id', $owner_id);
            })
            ->whereDate('created_at', now())
            ->latest()
            ->limit(5)
            ->get();

        return view('adminSide.owners.dashboard', compact(
            'tenantsCount',
            'roomsCount',
            'leasesCount',
            'roomStatusStats',
            'payments'
        ));
    }
}
