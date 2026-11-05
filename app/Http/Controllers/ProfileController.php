<?php

namespace App\Http\Controllers;

use App\Http\Requests\{CompanyUpdateRequest, ProfileUpdateRequest};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use App\Models\UserManagement;
use App\Services\{BankAccountService, CompanyService};
use App\Http\Requests\BankAccountUpdateRequest;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, BankAccountService $bankAccountService): View
    {
        $user = $request->user();
        $effectiveUser = get_effective_user();
        $userManagement = $effectiveUser
            ? UserManagement::where('user_id', $effectiveUser->id)->first()
            : null;

        return view('profile.edit', [
            'user' => $user,
            'userManagement' => $userManagement,
            'company' => $effectiveUser?->companies()->first(),
            'bankDetail' => $effectiveUser
                ? $bankAccountService->getDefaultForUser($effectiveUser)
                : null,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Update the company belonging to the effective account user.
     */
    public function updateCompany(
        CompanyUpdateRequest $request,
        CompanyService $companyService
    ): RedirectResponse
    {
        $effectiveUser = get_effective_user();

        if (!$effectiveUser) {
            abort(403, 'Unable to determine the account owner.');
        }

        $companyService->saveForUser(
            $effectiveUser,
            $request->validated(),
            $request->file('company_logo')
        );

        return Redirect::route('profile.edit')->with('status', 'company-updated');
    }

    public function updateBankAccount(
        BankAccountUpdateRequest $request,
        BankAccountService $bankAccountService
    ): RedirectResponse {
        $effectiveUser = get_effective_user();

        if (!$effectiveUser) {
            abort(403, 'Unable to determine the account owner.');
        }

        $bankAccountService->saveForUser($effectiveUser, $request->validated());

        return Redirect::route('profile.edit')->with('status', 'bank-account-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->update([
            'status' => 'inactive',
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
