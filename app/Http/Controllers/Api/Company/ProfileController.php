<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyProfileUpdateRequest;
use App\Http\Requests\StoreSettingRequest;
use App\Services\Company\ProfileServices;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        protected ProfileServices $profileService
    ) {}

    /**
     * Get company profile.
     */
    public function profile(Request $request)
    {
        $result = $this->profileService->profile(
            $request->user()
        );

        return $this->apiResponse(
            true,
            'Profile fetched successfully.',
            $result,
            200
        );
    }

    /**
     * Update company profile.
     */

    public function updateProfile(CompanyProfileUpdateRequest $request)
    {
        $result = $this->profileService->updateCompanyProfile($request->user(), $request->validated());

        return $this->apiResponse(
            true,
            'Company profile updated successfully.',
            $result,
            200
        );
    }


    /**
     * Update company profile.
     */
    public function getStoreSetting(Request $request)
    {
        $storeSetting = $this->profileService->getStoreSetting(
            $request->user()
        );

        return $this->apiResponse(
            true,
            'Store settings fetched successfully.',
            $storeSetting,
            200
        );
    }

    /**
     * Update company store settings.
     */
    public function updateStoreSetting(StoreSettingRequest $request) 
    {
        $company = auth()->user()->company;

        $storeSetting = $this->profileService->updateStoreSetting(
            $company,
            $request->validated()
        );

        return $this->apiResponse(
            true,
            'Store settings updated successfully.',
            $storeSetting
        );
    }
}
