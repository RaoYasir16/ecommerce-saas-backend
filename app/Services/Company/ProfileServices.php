<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\Role;
use App\Models\StoreSetting;
use App\Models\User;
use App\Traits\FileUploadTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProfileServices
{
    use FileUploadTrait;

    /**
     * Get authenticated profile.
     */
    public function profile(User $user): array
    {
        $user->load('company');

        return [
            'user' => $user,
            'company' => $user->company,
        ];
    }


    /*
     * Update Company Profile
     */

    public function updateCompanyProfile(
        User $user,
        array $data
    ): array {
        return DB::transaction(function () use ($user, $data) {

            $company = $user->company;

            if (!$company) {
                throw new \Exception(
                    'Company not found.',
                    404
                );
            }

            /*
             * Logo Upload
             */
            if (isset($data['logo']) && $data['logo']) {

                if ($company->logo) {
                    $this->deleteFile($company->logo);
                }

                $data['logo'] = $this->uploadFile(
                    $data['logo'],
                    'companies/logos'
                );
            }

            /*
             * Update Company
             */
            $company->update([
                'name' => $data['name'],
                'email' => $data['email'] ?? $company->email,
                'whatsapp_number' => $data['whatsapp_number']
                    ?? $company->whatsapp_number,
                'address' => $data['address']
                    ?? $company->address,
                'logo' => $data['logo']
                    ?? $company->logo,
            ]);

            $user->load('company');

            return [
                'user' => $this->formatUser($user),
                'company' => $this->formatCompany($company),
            ];
        });
    }

    /**
     * Format user response.
     */
    protected function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'company_id' => $user->company_id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'role' => $user->role,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * Format company response.
     */
    protected function formatCompany($company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'subdomain' => $company->subdomain,
            'logo' => $company->logo,
            'logo_url' => $company->logo_url,
            'whatsapp_number' => $company->whatsapp_number,
            'email' => $company->email,
            'address' => $company->address,
            'trial_ends_at' => $company->trial_ends_at,
            'status' => $company->status,
            'created_at' => $company->created_at,
            'updated_at' => $company->updated_at,
        ];
    }
}
