<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyProfileUpdateRequest;
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
}
