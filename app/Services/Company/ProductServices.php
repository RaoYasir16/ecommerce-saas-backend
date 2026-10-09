<?php

namespace App\Services\Company;

use App\Models\ProductCategory;
use App\Traits\FileUploadTrait;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductServices
{
    use FileUploadTrait;

    /**
     * Add new category
     */
    public function addCategory($company, array $data): ProductCategory
    {
        return DB::transaction(function () use ($company, $data) {

            $imagePath = null;

            if (isset($data['image'])) {
                $imagePath = $this->uploadFile(
                    $data['image'],
                    'categories'
                );
            }

            return ProductCategory::create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'image' => $imagePath,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    /**
     * Get all categories
     */
    public function getAllCategories($company)
    {
        return ProductCategory::where('company_id', $company->id)
            ->latest()
            ->get();
    }

    /**
     * Delete category
     */
    public function deleteCategory($company, int $id): void
    {
        $category = ProductCategory::where('company_id', $company->id)->find($id);

        if(!$category){
            throw new HttpException(404,'Product Category not found');
        }
        // Delete category image
        if (!empty($category->image)) {
            $this->deleteFile($category->image);
        }
        // Delete category
        $category->delete();
    }
}
