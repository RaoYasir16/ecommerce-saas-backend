<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PlatformSetting;
use App\Models\WholesaleCategory;
use App\Models\WholesaleOrder;
use App\Models\WholesaleProduct;
use App\Services\Company\WalletService;
use App\Traits\FileUploadTrait;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WholesaleService
{
    use FileUploadTrait;

    public function __construct(
        protected WalletService $walletService
    ) {}

    // ==========================================
    // CATEGORIES
    // ==========================================

    public function listCategories(array $filters = [], bool $onlyActive = false)
    {
        $query = WholesaleCategory::query()
            ->withCount([
                'products' => function ($q) use ($onlyActive) {
                    if ($onlyActive) {
                        $q->where('is_active', true);
                    }
                },
            ]);

        if ($onlyActive) {
            $query->where('is_active', true);
        } elseif (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== 'all') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc');

        if (!empty($filters['all']) && filter_var($filters['all'], FILTER_VALIDATE_BOOLEAN)) {
            return $query->get();
        }

        if ($onlyActive && empty($filters['per_page'])) {
            return $query->get();
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 50), 1), 100);

        return $query->paginate($perPage);
    }

    public function createCategory(array $data, ?UploadedFile $imageFile = null): WholesaleCategory
    {
        $slugSource = !empty($data['slug']) ? $data['slug'] : $data['name'];
        $slug = $this->makeUniqueSlug(WholesaleCategory::class, $slugSource);

        $imagePath = $this->handleImageUpload($imageFile, $data['image_url'] ?? ($data['image'] ?? null), null, 'wholesale/categories');

        $category = WholesaleCategory::create([
            'name' => trim((string) $data['name']),
            'slug' => $slug,
            'image' => $imagePath,
            'description' => $data['description'] ?? null,
            'is_active' => array_key_exists('is_active', $data)
                ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN)
                : true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return $category->loadCount('products');
    }

    public function updateCategory(int $id, array $data, ?UploadedFile $imageFile = null): WholesaleCategory
    {
        $category = WholesaleCategory::findOrFail($id);

        $updateData = [];

        if (array_key_exists('name', $data)) {
            $updateData['name'] = trim((string) $data['name']);
        }

        if (!empty($data['slug'])) {
            $updateData['slug'] = $this->makeUniqueSlug(WholesaleCategory::class, $data['slug'], $category->id);
        } elseif (!empty($data['name']) && $data['name'] !== $category->name && empty($data['preserve_slug'])) {
            // Keep existing slug unless explicitly changed to preserve external category filters
        }

        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'];
        }

        if (array_key_exists('sort_order', $data)) {
            $updateData['sort_order'] = (int) $data['sort_order'];
        }

        if (array_key_exists('is_active', $data)) {
            $updateData['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        if ($imageFile || array_key_exists('image_url', $data) || array_key_exists('image', $data) || !empty($data['remove_image'])) {
            if (!empty($data['remove_image'])) {
                $this->deleteLocalImageIfOwned($category->image);
                $updateData['image'] = null;
            } else {
                $newPath = $this->handleImageUpload(
                    $imageFile,
                    $data['image_url'] ?? ($data['image'] ?? null),
                    $category->image,
                    'wholesale/categories'
                );
                if ($newPath !== $category->image) {
                    $updateData['image'] = $newPath;
                }
            }
        }

        $category->update($updateData);

        return $category->fresh()->loadCount('products');
    }

    public function deleteCategory(int $id): void
    {
        $category = WholesaleCategory::withCount('products')->findOrFail($id);

        if ($category->products_count > 0) {
            throw ValidationException::withMessages([
                'category' => ["Cannot delete '{$category->name}' because {$category->products_count} wholesale product(s) belong to it. Move or delete those products first."],
            ]);
        }

        $this->deleteLocalImageIfOwned($category->image);
        $category->delete();
    }

    // ==========================================
    // PRODUCTS
    // ==========================================

    public function listProducts(array $filters = [], bool $onlyActive = false)
    {
        $query = WholesaleProduct::query()->with('category');

        if ($onlyActive) {
            $query->where('is_active', true)
                ->whereHas('category', fn ($q) => $q->where('is_active', true));
        } elseif (isset($filters['is_active']) && $filters['is_active'] !== '' && $filters['is_active'] !== 'all') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['in_stock']) && $filters['in_stock'] !== '' && $filters['in_stock'] !== 'all') {
            $query->where('in_stock', filter_var($filters['in_stock'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['category_id']) && $filters['category_id'] !== 'all') {
            $query->where('category_id', (int) $filters['category_id']);
        } elseif (!empty($filters['category']) && $filters['category'] !== 'all') {
            $catFilter = trim((string) $filters['category']);
            if (is_numeric($catFilter)) {
                $query->where('category_id', (int) $catFilter);
            } else {
                $query->whereHas('category', fn ($q) => $q->where('slug', $catFilter));
            }
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        $query->latest();

        if (!empty($filters['all']) && filter_var($filters['all'], FILTER_VALIDATE_BOOLEAN)) {
            return $query->get();
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->paginate($perPage);
    }

    public function getProduct(int $id, bool $onlyActive = false): WholesaleProduct
    {
        $query = WholesaleProduct::query()->with('category')->where('id', $id);
        if ($onlyActive) {
            $query->where('is_active', true);
        }

        return $query->firstOrFail();
    }

    public function createProduct(array $data, ?UploadedFile $imageFile = null): WholesaleProduct
    {
        WholesaleCategory::findOrFail((int) $data['category_id']);

        $slugSource = !empty($data['slug']) ? $data['slug'] : $data['title'];
        $slug = $this->makeUniqueSlug(WholesaleProduct::class, $slugSource);

        $sku = strtoupper(trim((string) ($data['sku'] ?? '')));
        if ($sku === '') {
            $sku = 'WHOLE-' . strtoupper(Str::random(6));
        }

        if (WholesaleProduct::where('sku', $sku)->exists()) {
            throw ValidationException::withMessages([
                'sku' => ['This wholesale SKU already exists. Please use a unique SKU.'],
            ]);
        }

        $currency = Company::normalizeCurrency($data['currency'] ?? PlatformSetting::first()?->currency ?? 'PKR');
        $stock = max(0, (int) ($data['stock'] ?? 0));
        $moq = max(1, (int) ($data['moq'] ?? 1));

        $imagePath = $this->handleImageUpload(
            $imageFile,
            $data['image_url'] ?? ($data['image'] ?? null),
            null,
            'wholesale/products'
        );

        $inStock = array_key_exists('in_stock', $data)
            ? filter_var($data['in_stock'], FILTER_VALIDATE_BOOLEAN)
            : ($stock > 0);
        if ($stock <= 0) {
            $inStock = false;
        }

        $product = WholesaleProduct::create([
            'category_id' => (int) $data['category_id'],
            'title' => trim((string) $data['title']),
            'slug' => $slug,
            'sku' => $sku,
            'description' => $data['description'] ?? null,
            'wholesale_price' => round((float) $data['wholesale_price'], 2),
            'retail_price' => isset($data['retail_price']) && $data['retail_price'] !== ''
                ? round((float) $data['retail_price'], 2)
                : null,
            'currency' => $currency,
            'moq' => $moq,
            'stock' => $stock,
            'image' => $imagePath,
            'colors' => $this->parseTagList($data['colors'] ?? []),
            'sizes' => $this->parseTagList($data['sizes'] ?? []),
            'is_active' => array_key_exists('is_active', $data)
                ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN)
                : true,
            'in_stock' => $inStock,
        ]);

        return $product->load('category');
    }

    public function updateProduct(int $id, array $data, ?UploadedFile $imageFile = null): WholesaleProduct
    {
        $product = WholesaleProduct::findOrFail($id);

        if (!empty($data['category_id'])) {
            WholesaleCategory::findOrFail((int) $data['category_id']);
        }

        if (!empty($data['sku'])) {
            $normalizedSku = strtoupper(trim((string) $data['sku']));
            $exists = WholesaleProduct::where('sku', $normalizedSku)
                ->where('id', '!=', $product->id)
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'sku' => ['Another wholesale product already uses this SKU.'],
                ]);
            }
            $data['sku'] = $normalizedSku;
        }

        $updateData = [];
        foreach (['category_id', 'title', 'sku', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }

        if (!empty($data['slug'])) {
            $updateData['slug'] = $this->makeUniqueSlug(WholesaleProduct::class, $data['slug'], $product->id);
        }

        if (array_key_exists('wholesale_price', $data)) {
            $updateData['wholesale_price'] = round((float) $data['wholesale_price'], 2);
        }
        if (array_key_exists('retail_price', $data)) {
            $updateData['retail_price'] = ($data['retail_price'] === '' || $data['retail_price'] === null)
                ? null
                : round((float) $data['retail_price'], 2);
        }
        if (array_key_exists('currency', $data) && !empty($data['currency'])) {
            $updateData['currency'] = Company::normalizeCurrency($data['currency']);
        }
        if (array_key_exists('moq', $data)) {
            $updateData['moq'] = max(1, (int) $data['moq']);
        }
        if (array_key_exists('stock', $data)) {
            $updateData['stock'] = max(0, (int) $data['stock']);
        }
        if (array_key_exists('is_active', $data)) {
            $updateData['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('in_stock', $data)) {
            $updateData['in_stock'] = filter_var($data['in_stock'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('colors', $data)) {
            $updateData['colors'] = $this->parseTagList($data['colors']);
        }
        if (array_key_exists('sizes', $data)) {
            $updateData['sizes'] = $this->parseTagList($data['sizes']);
        }

        $effectiveStock = $updateData['stock'] ?? $product->stock;
        if ($effectiveStock <= 0) {
            $updateData['in_stock'] = false;
        }

        if ($imageFile || array_key_exists('image_url', $data) || array_key_exists('image', $data) || !empty($data['remove_image'])) {
            if (!empty($data['remove_image'])) {
                $this->deleteLocalImageIfOwned($product->image);
                $updateData['image'] = null;
            } else {
                $newPath = $this->handleImageUpload(
                    $imageFile,
                    $data['image_url'] ?? ($data['image'] ?? null),
                    $product->image,
                    'wholesale/products'
                );
                if ($newPath !== $product->image) {
                    $updateData['image'] = $newPath;
                }
            }
        }

        $product->update($updateData);

        return $product->fresh()->load('category');
    }

    public function deleteProduct(int $id): void
    {
        $product = WholesaleProduct::findOrFail($id);
        $this->deleteLocalImageIfOwned($product->image);
        $product->delete();
    }

    // ==========================================
    // WHOLESALE ORDERS
    // ==========================================

    public function listOrders(array $filters = [], ?int $companyId = null)
    {
        $query = WholesaleOrder::query()
            ->with([
                'company:id,name,subdomain,logo,email,whatsapp_number',
                'user:id,name,email,phone',
                'product:id,title,sku,image,stock',
            ]);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        } elseif (!empty($filters['company_id'])) {
            $query->where('company_id', (int) $filters['company_id']);
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', trim((string) $filters['status']));
        }

        if (!empty($filters['payment_status']) && $filters['payment_status'] !== 'all') {
            $query->where('payment_status', trim((string) $filters['payment_status']));
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('product_title', 'like', "%{$search}%")
                    ->orWhere('product_sku', 'like', "%{$search}%")
                    ->orWhereHas('company', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('subdomain', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->latest()->paginate($perPage);
    }

    public function getOrder(int $id, ?int $companyId = null): WholesaleOrder
    {
        $query = WholesaleOrder::query()
            ->with([
                'company:id,name,subdomain,logo,email,whatsapp_number,address',
                'user:id,name,email,phone',
                'product',
            ])
            ->where('id', $id);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->firstOrFail();
    }

    public function placeMerchantOrder($company, $user, array $data): WholesaleOrder
    {
        if (!$company) {
            throw new HttpException(404, 'Merchant company not found.');
        }

        return DB::transaction(function () use ($company, $user, $data) {
            $product = WholesaleProduct::where('id', (int) $data['wholesale_product_id'])
                ->lockForUpdate()
                ->first();

            if (!$product) {
                throw new HttpException(404, 'Wholesale product not found.');
            }

            if (!$product->is_active || !$product->in_stock || $product->stock <= 0) {
                throw ValidationException::withMessages([
                    'wholesale_product_id' => ['This wholesale product is currently unavailable or out of stock.'],
                ]);
            }

            $quantity = (int) $data['quantity'];
            if ($quantity < $product->moq) {
                throw ValidationException::withMessages([
                    'quantity' => ["Minimum order quantity for this item is {$product->moq} units."],
                ]);
            }

            if ($quantity > $product->stock) {
                throw ValidationException::withMessages([
                    'quantity' => ["Only {$product->stock} units are currently available in wholesale stock."],
                ]);
            }

            $unitPrice = (float) $product->wholesale_price;
            $totalAmount = round($unitPrice * $quantity, 2);
            $currency = $product->currency ?: (PlatformSetting::first()?->currency ?: 'PKR');
            $paymentMethod = trim((string) ($data['payment_method'] ?? 'Bank Transfer'));
            $orderNumber = 'WS-ORD-' . strtoupper(Str::random(7));

            $paymentStatus = 'pending';
            if ($paymentMethod === 'Merchant Wallet') {
                try {
                    $this->walletService->debit(
                        $company,
                        $totalAmount,
                        'wholesale_order',
                        "Wholesale order {$orderNumber}: {$product->title} (x{$quantity})",
                        $orderNumber,
                        [
                            'wholesale_product_id' => $product->id,
                            'quantity' => $quantity,
                        ]
                    );
                    $paymentStatus = 'paid';
                } catch (Exception $e) {
                    throw ValidationException::withMessages([
                        'payment_method' => [$e->getMessage()],
                    ]);
                }
            }

            $newStock = max(0, $product->stock - $quantity);
            $product->update([
                'stock' => $newStock,
                'in_stock' => $newStock > 0,
            ]);

            $order = WholesaleOrder::create([
                'order_number' => $orderNumber,
                'company_id' => $company->id,
                'user_id' => $user?->id,
                'wholesale_product_id' => $product->id,
                'product_title' => $product->title,
                'product_sku' => $product->sku,
                'product_image' => $product->image,
                'selected_color' => $data['selected_color'] ?? ($product->colors[0] ?? 'Standard'),
                'selected_size' => $data['selected_size'] ?? ($product->sizes[0] ?? 'Standard'),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'status' => 'pending',
                'shipping_address' => trim((string) $data['shipping_address']),
                'whatsapp_phone' => trim((string) ($data['whatsapp_phone'] ?? ($company->whatsapp_number ?? ''))),
                'notes' => $data['notes'] ?? null,
            ]);

            return $order->load([
                'company:id,name,subdomain,logo,email,whatsapp_number',
                'user:id,name,email,phone',
                'product',
            ]);
        });
    }

    public function updateOrderStatus(int $id, array $data): WholesaleOrder
    {
        return DB::transaction(function () use ($id, $data) {
            $order = WholesaleOrder::where('id', $id)->lockForUpdate()->firstOrFail();

            $newStatus = isset($data['status']) ? trim((string) $data['status']) : $order->status;
            $newPaymentStatus = isset($data['payment_status']) ? trim((string) $data['payment_status']) : $order->payment_status;
            $previousStatus = $order->status;

            // Handle stock restoration & wallet refund when cancelling an active order
            if ($newStatus === 'cancelled' && $previousStatus !== 'cancelled') {
                if ($order->wholesale_product_id) {
                    $product = WholesaleProduct::where('id', $order->wholesale_product_id)
                        ->lockForUpdate()
                        ->first();
                    if ($product) {
                        $product->update([
                            'stock' => $product->stock + $order->quantity,
                            'in_stock' => true,
                        ]);
                    }
                }

                if ($order->payment_method === 'Merchant Wallet' && $order->payment_status === 'paid') {
                    $this->walletService->credit(
                        $order->company_id,
                        (float) $order->total_amount,
                        'wholesale_refund',
                        "Refund for cancelled wholesale order {$order->order_number}",
                        $order->order_number,
                        ['wholesale_order_id' => $order->id]
                    );
                    if (!isset($data['payment_status'])) {
                        $newPaymentStatus = 'refunded';
                    }
                }

                $order->cancelled_at = now();
            } elseif ($previousStatus === 'cancelled' && $newStatus !== 'cancelled') {
                if ($order->wholesale_product_id) {
                    $product = WholesaleProduct::where('id', $order->wholesale_product_id)
                        ->lockForUpdate()
                        ->first();
                    if ($product) {
                        if ($product->stock < $order->quantity) {
                            throw ValidationException::withMessages([
                                'status' => ["Insufficient wholesale stock ({$product->stock}) to reopen order for {$order->quantity} units."],
                            ]);
                        }
                        $remaining = $product->stock - $order->quantity;
                        $product->update([
                            'stock' => $remaining,
                            'in_stock' => $remaining > 0,
                        ]);
                    }
                }
                $order->cancelled_at = null;
            }

            $order->status = $newStatus;
            $order->payment_status = $newPaymentStatus;
            if (array_key_exists('admin_notes', $data)) {
                $order->admin_notes = $data['admin_notes'];
            }
            $order->save();

            return $order->fresh()->load([
                'company:id,name,subdomain,logo,email,whatsapp_number',
                'user:id,name,email,phone',
                'product',
            ]);
        });
    }

    // ==========================================
    // HELPERS
    // ==========================================

    private function makeUniqueSlug(string $modelClass, string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'item';
        $slug = $base;
        $counter = 1;

        while (
            $modelClass::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function parseTagList(mixed $raw): array
    {
        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return [];
            }
            $decoded = json_decode($trimmed, true);
            $items = is_array($decoded) ? $decoded : explode(',', $trimmed);
        } elseif (is_array($raw)) {
            $items = $raw;
        } else {
            return [];
        }

        $clean = [];
        foreach ($items as $item) {
            $text = trim((string) $item);
            if ($text !== '' && !in_array($text, $clean, true)) {
                $clean[] = $text;
            }
        }

        return $clean;
    }

    private function handleImageUpload(
        ?UploadedFile $imageFile,
        ?string $imageUrl,
        ?string $existingImage,
        string $directory
    ): ?string {
        if ($imageFile instanceof UploadedFile) {
            $this->deleteLocalImageIfOwned($existingImage);
            return $this->uploadFile($imageFile, $directory);
        }

        if ($imageUrl !== null && trim($imageUrl) !== '') {
            $cleanUrl = trim($imageUrl);
            if ($cleanUrl !== $existingImage) {
                $this->deleteLocalImageIfOwned($existingImage);
            }
            return $cleanUrl;
        }

        return $existingImage;
    }

    private function deleteLocalImageIfOwned(?string $path): void
    {
        if (
            !empty($path) &&
            !str_starts_with($path, 'http://') &&
            !str_starts_with($path, 'https://') &&
            !str_starts_with($path, 'data:')
        ) {
            $this->deleteFile($path);
        }
    }
}

