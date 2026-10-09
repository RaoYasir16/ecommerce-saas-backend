<?php

namespace App\Services\Company;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Traits\FileUploadTrait;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

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


    //==========================>  Products Code <=============================//

    public function getAllProducts($company, array $filters = [])
    {
        $query = Product::query()
            ->where('company_id', $company->id)
            ->with(['category', 'images', 'variants']);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['is_available'])) {
            $query->where(
                'is_available',
                filter_var($filters['is_available'], FILTER_VALIDATE_BOOLEAN)
            );
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->latest()->paginate($perPage);
    }

    public function getProductById($company, int $id): Product
    {
        $product =  Product::where('company_id', $company->id)
            ->with(['category', 'images', 'variants'])
            ->find($id);

            if(!$product){
                throw new HttpException(404,'Product not found');
            }

            return $product;
    }

    public function createProduct($company, array $data): Product
    {
        return DB::transaction(function () use ($company, $data) {
            $this->validateCompanyCategory(
                $company->id,
                $data['category_id']
            );

            $product = Product::create([
                'company_id' => $company->id,
                'title' => $data['title'],
                'category_id' => $data['category_id'],
                'slug' => $this->makeUniqueSlug(
                    $company->id,
                    $data['slug']
                ),
                'description' => $data['description'] ?? null,
                'stock' => $data['stock'] ?? 0,
                'regular_price' => $data['regular_price'],
                'sale_price' => $data['sale_price'] ?? null,
                'is_available' => $data['is_available'] ?? true,
                'has_variants' => $data['has_variants'] ?? false,
            ]);

            if ($product->has_variants) {
                $this->syncVariants($product, $data['variants'] ?? []);
            }

            if (!empty($data['images'])) {
                $this->storeImages($product, $data['images']);
            }

            return $product->load(['category', 'images', 'variants']);
        });
    }

    public function updateProduct($company, int $id, array $data): Product
    {
        return DB::transaction(function () use ($company, $id, $data) {
            $product = Product::where('company_id', $company->id)
                ->findOrFail($id);

            if (isset($data['category_id'])) {
                $this->validateCompanyCategory(
                    $company->id,
                    $data['category_id']
                );
            }

            $updateData = [];

            foreach ([
                'title',
                'category_id',
                'description',
                'stock',
                'regular_price',
                'sale_price',
                'is_available',
                'has_variants',
            ] as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            if (isset($data['slug'])) {
                $updateData['slug'] = $this->makeUniqueSlug(
                    $company->id,
                    $data['slug'],
                    $product->id
                );
            }

            $product->update($updateData);

            if (array_key_exists('variants', $data)) {
                if ($product->has_variants) {
                    $this->syncVariants($product, $data['variants'] ?? []);
                } else {
                    $product->variants()->delete();
                }
            } elseif (array_key_exists('has_variants', $data) && !$product->has_variants) {
                $product->variants()->delete();
            }

            // Images are replaced only when the images key is submitted.
            if (array_key_exists('images', $data)) {
                $this->replaceImages($product, $data['images'] ?? []);
            }

            return $product->fresh()->load([
                'category',
                'images',
                'variants',
            ]);
        });
    }

    public function deleteProduct($company, int $id): void
    {
        DB::transaction(function () use ($company, $id) {
            $product = Product::where('company_id', $company->id)
                ->with('images')
                ->find($id);

                if(!$product){
                    throw new HttpException(404,'Product not found');
                }

            foreach ($product->images as $image) {
                $this->deleteFile($image->image_path);
            }

            $product->images()->delete();
            $product->variants()->delete();
            $product->delete();
        });
    }

    protected function validateCompanyCategory(int $companyId, int $categoryId): void
    {
        $exists = ProductCategory::where('company_id', $companyId)
            ->whereKey($categoryId)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'category_id' => 'Selected category does not belong to your company.',
            ]);
        }
    }

    protected function makeUniqueSlug(
        int $companyId,
        string $slug,
        ?int $ignoreProductId = null
    ): string {
        $slug = Str::slug($slug);

        $query = Product::where('company_id', $companyId)
            ->whereRaw('LOWER(slug) = ?', [mb_strtolower($slug)]);

        if ($ignoreProductId) {
            $query->where('id', '!=', $ignoreProductId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'slug' => 'This product slug already exists for your company.',
            ]);
        }

        return $slug;
    }

    protected function syncVariants(Product $product, array $variants): void
    {
        // Replace the existing variants with the submitted list.
        $product->variants()->delete();

        foreach ($variants as $variant) {
            $product->variants()->create([
                'sku' => $variant['sku'] ?? null,
                'regular_price' => $variant['regular_price'] ?? null,
                'sale_price' => $variant['sale_price'] ?? null,
                'stock' => $variant['stock'] ?? 0,
                'attributes' => $variant['attributes'] ?? [],
                'is_available' => $variant['is_available'] ?? true,
            ]);
        }
    }

    protected function storeImages(Product $product, array $images): void
    {
        foreach ($images as $index => $image) {
            if (!$image instanceof UploadedFile) {
                continue;
            }

            $path = $this->uploadFile($image, 'products');

            $product->images()->create([
                'image_path' => $path,
                'is_primary' => $index === 0,
                'sort_order' => $index,
            ]);
        }
    }

    protected function replaceImages(Product $product, array $images): void
    {
        foreach ($product->images as $oldImage) {
            $this->deleteFile($oldImage->image_path);
            $oldImage->delete();
        }

        $this->storeImages($product, $images);
    }
}
