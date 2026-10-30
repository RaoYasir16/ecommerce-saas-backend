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
use Symfony\Component\HttpKernel\Exception\HttpException;

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

    /**
     * Get company store settings.
     */
    public function getStoreSetting(User $user)
    {
        $company = $user->company;

        if (!$company) {
            throw new \Exception(
                'Company not found.',
                404
            );
        }

        $storeSetting = $company->storeSetting;

        if (!$storeSetting) {
            $storeSetting = StoreSetting::firstOrCreate([
                'company_id' => $company->id,
            ]);
        }

        return $storeSetting;
    }

    /**
     * Update company store settings.
     */
    public function updateStoreSetting($company, array $data): StoreSetting
    {
        return DB::transaction(function () use ($company, $data) {

            $storeSetting = StoreSetting::firstOrCreate([
                'company_id' => $company->id,
            ]);

            // banner logic
            $hasNewBanners = isset($data['banners']) && is_array($data['banners']);
            $hasExistingBanners = array_key_exists('existing_banners', $data);

            if ($hasNewBanners || $hasExistingBanners) {
                $keptBanners = [];
                $existingPathsToKeep = [];

                if ($hasExistingBanners) {
                    if (is_array($data['existing_banners'])) {
                        $existingPathsToKeep = $data['existing_banners'];
                    } elseif (is_string($data['existing_banners'])) {
                        $decoded = json_decode($data['existing_banners'], true);
                        $existingPathsToKeep = is_array($decoded) ? $decoded : [];
                    }
                }

                // Delete old banners that are NOT in existingPathsToKeep
                foreach ($storeSetting->banners ?? [] as $banner) {
                    $path = $banner['image_path'] ?? null;
                    if ($path) {
                        if ($hasExistingBanners && in_array($path, $existingPathsToKeep)) {
                            $keptBanners[] = ['image_path' => $path];
                        } elseif (!$hasExistingBanners) {
                            // If existing_banners was not sent, full replace: delete old banner
                            $this->deleteFile($path);
                        } else {
                            // In existing_banners array, but this path was removed
                            $this->deleteFile($path);
                        }
                    }
                }

                // Upload new banners
                if ($hasNewBanners) {
                    foreach ($data['banners'] as $banner) {
                        if ($banner) {
                            $path = $this->uploadFile(
                                $banner,
                                'store-banners'
                            );

                            $keptBanners[] = [
                                'image_path' => $path,
                            ];
                        }
                    }
                }

                // Replace banners array in update data
                $data['banners'] = $keptBanners;
                unset($data['existing_banners']);
            }

            $storeSetting->update($data);

            return $storeSetting->fresh();
        });
    }
}
