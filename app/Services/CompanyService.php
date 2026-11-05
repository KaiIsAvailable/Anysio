<?php

namespace App\Services;

use App\Models\{Company, CompanyUser, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompanyService
{
    public function saveForTenant(User $user, array $data, ?UploadedFile $certificate = null, ?UploadedFile $logo = null): Company
    {
        return DB::transaction(function () use ($user, $data, $certificate, $logo): Company {
            $company = $user->companies()->first();
            $company ??= new Company();
            $oldPath = $company->ssm_cert_path;
            $newPath = null;
            $newLogoPath = null;
            $oldLogoPath = $company->logo_path;

            try {
                $company->fill([
                    'name' => $data['company_name'],
                    'ssm_new_number' => $data['ssm_new_number'],
                    'address' => $data['company_address'],
                ]);

                foreach (['ssm_old_number' => 'ssm_old_number', 'company_phone' => 'phone', 'company_email' => 'email'] as $input => $attribute) {
                    if (array_key_exists($input, $data)) {
                        $company->{$attribute} = $data[$input];
                    }
                }

                if ($logo) {
                    $newLogoPath = $logo->store('companies/logos', 'public');
                    if (!$newLogoPath) {
                        throw new \RuntimeException('Unable to upload the company logo.');
                    }
                    $company->logo_path = $newLogoPath;
                }

                if ($certificate) {
                    $newPath = app(FileService::class)->upload($certificate, $user->id, 'tenant_ssm');
                    if (!$newPath) {
                        throw new \RuntimeException('Unable to upload the SSM certificate.');
                    }
                    $company->ssm_cert_path = $newPath;
                }

                $company->save();
                $user->companies()->syncWithoutDetaching([$company->id]);

                if ($newPath && $oldPath && $oldPath !== $newPath) {
                    DB::afterCommit(fn () => app(FileService::class)->delete($oldPath));
                }

                if ($newLogoPath && $oldLogoPath) {
                    DB::afterCommit(fn () => Storage::disk('public')->delete($oldLogoPath));
                }

                return $company;
            } catch (\Throwable $exception) {
                if ($newLogoPath) {
                    Storage::disk('public')->delete($newLogoPath);
                }
                if ($newPath) {
                    app(FileService::class)->delete($newPath);
                }
                throw $exception;
            }
        });
    }

    /**
     * Create or update the company associated with the effective account user.
     *
     * @param array<string, mixed> $data
     */
    public function saveForUser(User $user, array $data, ?UploadedFile $logo = null): Company
    {
        return DB::transaction(function () use ($user, $data, $logo): Company {
            $company = $user->companies()->first();

            if (!$company) {
                $company = Company::create([
                    'name' => $data['company_name'],
                ]);

                CompanyUser::create([
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                ]);
            }

            $oldLogoPath = $company->logo_path;

            $company->fill([
                'name' => $data['company_name'],
                'ssm_old_number' => $data['ssm_old_number'] ?? null,
                'ssm_new_number' => $data['ssm_new_number'] ?? null,
                'address' => $data['company_address'] ?? null,
                'phone' => $data['company_phone'] ?? null,
                'email' => $data['company_email'] ?? null,
            ]);

            if ($logo) {
                $company->logo_path = $logo->store('companies/logos', 'public');
            }

            $company->save();

            if ($logo && $oldLogoPath) {
                Storage::disk('public')->delete($oldLogoPath);
            }

            return $company;
        });
    }
}
