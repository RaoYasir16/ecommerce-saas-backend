<?php

namespace App\Services\StoreCustomer;

use App\Models\CartItem;
use App\Models\Favorite;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StoreService
{
    public function getStore($company): array
    {
        return [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'subdomain' => $company->subdomain,
                'logo' => $company->logo,
                'logo_url' => $company->logo_url,
                'whatsapp_number' => $company->whatsapp_number,
                'email' => $company->email,
                'address' => $company->address,
                'status' => $company->status,
            ],
            'settings' => $company->storeSetting,
        ];
    }

    public function getProducts($company, array $filters = [])
    {
        $query = Product::query()
            ->where('company_id', $company->id)
            ->where('is_available', true)
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

        $perPage = min(max((int) ($filters['per_page'] ?? 12), 1), 100);

        return $query->latest()->paginate($perPage);
    }

    public function getProduct($company, string $slug): Product
    {
        return Product::query()
            ->where('company_id', $company->id)
            ->where('slug', $slug)
            ->where('is_available', true)
            ->with(['category', 'images', 'variants', 'reviews.user:id,name'])
            ->firstOrFail();
    }

    public function getCategories($company)
    {
        return ProductCategory::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->latest()
            ->get();
    }

    // ==========================================
    // FAVORITES / WISHLIST
    // ==========================================

    public function addFavorite($company, $user, array $data): array
    {
        $product = Product::query()
            ->where('company_id', $company->id)
            ->where('id', $data['product_id'])
            ->first();

        if (!$product) {
            throw ValidationException::withMessages([
                'product_id' => ['Product not found in this store.'],
            ]);
        }

        $favorite = Favorite::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($favorite) {
            $favorite->delete();

            return [
                'message' => 'Product removed from favorites successfully.',
                'data' => [
                    'product_id' => $product->id,
                    'is_favorite' => false,
                ],
            ];
        }

        $favorite = Favorite::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        return [
            'message' => 'Product added to favorites successfully.',
            'data' => $favorite->load([
                'product.images',
                'product.category',
                'product.variants',
            ])->setAttribute('is_favorite', true),
        ];
    }

    public function getFavorites($company, $user, array $filters = [])
    {
        $query = Favorite::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->with(['product.images', 'product.category', 'product.variants'])
            ->latest();

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->paginate($perPage);
    }

    // ==========================================
    // CART
    // ==========================================

    public function addToCart($company, $user, array $data): CartItem
    {
        $product = Product::query()
            ->where('company_id', $company->id)
            ->where('id', $data['product_id'])
            ->first();

        if (!$product) {
            throw ValidationException::withMessages([
                'product_id' => ['Product not found in this store.'],
            ]);
        }

        if (!$product->is_available) {
            throw ValidationException::withMessages([
                'product_id' => ['This product is currently unavailable.'],
            ]);
        }

        $quantity = max((int) ($data['quantity'] ?? 1), 1);
        $variant = null;

        if (!empty($data['product_variant_id'])) {
            $variant = ProductVariant::query()
                ->where('id', $data['product_variant_id'])
                ->where('product_id', $product->id)
                ->first();

            if (!$variant) {
                throw ValidationException::withMessages([
                    'product_variant_id' => ['Invalid product variant.'],
                ]);
            }

            if (!$variant->is_available) {
                throw ValidationException::withMessages([
                    'product_variant_id' => ['Selected variant is unavailable.'],
                ]);
            }
        } elseif ($product->has_variants) {
            throw ValidationException::withMessages([
                'product_variant_id' => ['Please select a product variant.'],
            ]);
        }

        // Check existing cart item
        $cartItem = CartItem::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->first();

        $targetQuantity = $cartItem ? ($cartItem->quantity + $quantity) : $quantity;
        $availableStock = $variant ? $variant->stock : $product->stock;

        if ($targetQuantity > $availableStock) {
            throw ValidationException::withMessages([
                'quantity' => ["Not enough stock available. Remaining stock: {$availableStock}"],
            ]);
        }

        if ($cartItem) {
            $cartItem->update(['quantity' => $targetQuantity]);
        } else {
            $cartItem = CartItem::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $quantity,
            ]);
        }

        return $cartItem->fresh(['product.images', 'product.category', 'variant']);
    }

    public function getCart($company, $user): array
    {
        $items = CartItem::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->with(['product.images', 'product.category', 'variant'])
            ->latest()
            ->get();

        $subtotal = 0;
        $totalQuantity = 0;

        foreach ($items as $item) {
            $subtotal += $item->subtotal;
            $totalQuantity += $item->quantity;
        }

        return [
            'items' => $items,
            'total_items' => $items->count(),
            'total_quantity' => $totalQuantity,
            'subtotal' => round($subtotal, 2),
        ];
    }

    public function removeFromCart($company, $user, int $cartItemId): bool
    {
        $cartItem = CartItem::query()
            ->where('id', $cartItemId)
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$cartItem) {
            throw new HttpException(404,
                'Cart item not found or invalid cart ID.'
            );
        }

        $cartItem->delete();

        return true;
    }

    public function updateCartQuantity($company, $user, array $data): CartItem
    {
        $targetId = $data['cart_item_id'] ?? $data['id'] ?? null;
        $productId = $data['product_id'] ?? null;
        $variantId = $data['product_variant_id'] ?? null;
        $quantity = (int) ($data['quantity'] ?? 1);

        $query = CartItem::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id);

        if ($targetId) {
            $query->where('id', $targetId);
        } elseif ($productId) {
            $query->where('product_id', $productId);
            if ($variantId !== null) {
                $query->where('product_variant_id', $variantId);
            }
        } else {
            throw ValidationException::withMessages([
                'cart_item_id' => ['Please specify cart_item_id or product_id.'],
            ]);
        }

        $cartItem = $query->first();

        if (!$cartItem) {
            throw new NotFoundHttpException('Cart item not found.');
        }

        if ($quantity <= 0) {
            $cartItem->delete();
            throw new NotFoundHttpException('Cart item removed due to non-positive quantity.');
        }

        // Validate stock
        $availableStock = $cartItem->variant ? $cartItem->variant->stock : $cartItem->product->stock;
        if ($quantity > $availableStock) {
            throw ValidationException::withMessages([
                'quantity' => ["Not enough stock available. Remaining stock: {$availableStock}"],
            ]);
        }

        $cartItem->update(['quantity' => $quantity]);

        return $cartItem->fresh(['product.images', 'product.category', 'variant']);
    }

    public function clearCart($company, $user): bool
    {
        CartItem::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->delete();

        return true;
    }

    // ==========================================
    // ORDERS
    // ==========================================

    public function placeOrder($company, $user, array $data): Order
    {
        return DB::transaction(function () use ($company, $user, $data) {
            $preparedItems = [];
            $cartItemsToDelete = [];

            // Case A: Custom items passed in request
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $product = Product::query()
                        ->where('company_id', $company->id)
                        ->where('id', $itemData['product_id'])
                        ->lockForUpdate()
                        ->first();

                    if (!$product || !$product->is_available) {
                        throw ValidationException::withMessages([
                            'items' => ["Product ID {$itemData['product_id']} is unavailable."],
                        ]);
                    }

                    $variant = null;
                    if (!empty($itemData['product_variant_id'])) {
                        $variant = ProductVariant::query()
                            ->where('id', $itemData['product_variant_id'])
                            ->where('product_id', $product->id)
                            ->lockForUpdate()
                            ->first();

                        if (!$variant || !$variant->is_available) {
                            throw ValidationException::withMessages([
                                'items' => ["Variant ID {$itemData['product_variant_id']} is unavailable."],
                            ]);
                        }
                    }

                    $qty = max((int) ($itemData['quantity'] ?? 1), 1);
                    $stock = $variant ? $variant->stock : $product->stock;

                    if ($qty > $stock) {
                        throw ValidationException::withMessages([
                            'items' => ["Insufficient stock for {$product->title}. Available: {$stock}"],
                        ]);
                    }

                    $unitPrice = (float) ($variant ? ($variant->sale_price ?? $variant->regular_price) : ($product->sale_price ?? $product->regular_price));
                    $itemSubtotal = round($unitPrice * $qty, 2);

                    $preparedItems[] = [
                        'product_id' => $product->id,
                        'product_variant_id' => $variant?->id,
                        'product_title' => $product->title,
                        'variant_sku' => $variant?->sku,
                        'variant_attributes' => $variant?->attributes,
                        'price' => $unitPrice,
                        'quantity' => $qty,
                        'subtotal' => $itemSubtotal,
                        'model_to_decrement' => $variant ?? $product,
                    ];
                }
            } else {
                // Case B: Checkout from current user cart
                $cartItems = CartItem::query()
                    ->where('company_id', $company->id)
                    ->where('user_id', $user->id)
                    ->with(['product', 'variant'])
                    ->lockForUpdate()
                    ->get();

                if ($cartItems->isEmpty()) {
                    throw ValidationException::withMessages([
                        'cart' => ['Your shopping cart is empty.'],
                    ]);
                }

                foreach ($cartItems as $cartItem) {
                    $product = $cartItem->product;
                    $variant = $cartItem->variant;

                    if (!$product || !$product->is_available) {
                        throw ValidationException::withMessages([
                            'cart' => ["Product '{$product?->title}' is no longer available."],
                        ]);
                    }

                    $qty = $cartItem->quantity;
                    $stock = $variant ? $variant->stock : $product->stock;

                    if ($qty > $stock) {
                        throw ValidationException::withMessages([
                            'cart' => ["Insufficient stock for '{$product->title}'. Available: {$stock}"],
                        ]);
                    }

                    $unitPrice = $cartItem->unit_price;
                    $itemSubtotal = $cartItem->subtotal;

                    $preparedItems[] = [
                        'product_id' => $product->id,
                        'product_variant_id' => $variant?->id,
                        'product_title' => $product->title,
                        'variant_sku' => $variant?->sku,
                        'variant_attributes' => $variant?->attributes,
                        'price' => $unitPrice,
                        'quantity' => $qty,
                        'subtotal' => $itemSubtotal,
                        'model_to_decrement' => $variant ?? $product,
                    ];

                    $cartItemsToDelete[] = $cartItem->id;
                }
            }

            // Calculate totals
            $subtotal = 0;
            foreach ($preparedItems as $item) {
                $subtotal += $item['subtotal'];
            }

            $shippingFee = max((float) ($data['shipping_fee'] ?? 0), 0);
            $tax = max((float) ($data['tax'] ?? 0), 0);
            $discount = max((float) ($data['discount'] ?? 0), 0);
            $totalAmount = max(0, round($subtotal + $shippingFee + $tax - $discount, 2));

            // Generate unique order number
            $orderNumber = 'ORD-' . strtoupper(Str::random(5)) . '-' . date('Ymd') . '-' . random_int(100, 999);

            $order = Order::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'order_number' => $orderNumber,
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $data['payment_method'] ?? 'cod',
                'customer_name' => $data['customer_name'] ?? $user->name,
                'customer_email' => $data['customer_email'] ?? $user->email,
                'customer_phone' => $data['customer_phone'],
                'shipping_address' => $data['shipping_address'],
                'city' => $data['city'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'tax' => $tax,
                'discount' => $discount,
                'total_amount' => $totalAmount,
            ]);

            // Save items & decrement stock
            foreach ($preparedItems as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'],
                    'product_title' => $item['product_title'],
                    'variant_sku' => $item['variant_sku'],
                    'variant_attributes' => $item['variant_attributes'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                ]);

                // Decrement stock
                $item['model_to_decrement']->decrement('stock', $item['quantity']);
            }

            // Clear ordered items from cart
            if (!empty($cartItemsToDelete)) {
                CartItem::whereIn('id', $cartItemsToDelete)->delete();
            }

            return $order->load(['items.product.images', 'items.variant']);
        });
    }

    public function getOrders($company, $user, array $filters = [])
    {
        $query = Order::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->with(['items.product.images', 'items.variant'])
            ->latest();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 100);

        return $query->paginate($perPage);
    }

    public function getOrder($company, $user, $orderId): Order
    {
        return Order::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('id', $orderId)
            ->with(['items.product.images', 'items.variant'])
            ->firstOrFail();
    }

    public function cancelOrder($company, $user, array $data, $orderId = null): Order
    {
        $targetId = $orderId ?? $data['order_id'] ?? $data['id'] ?? null;

        if (!$targetId) {
            throw ValidationException::withMessages([
                'order_id' => ['Order ID is required.'],
            ]);
        }

        return DB::transaction(function () use ($company, $user, $targetId, $data) {
            $order = Order::query()
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->where('id', $targetId)
                ->with('items')
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new NotFoundHttpException('Order not found.');
            }

            if ($order->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'order' => ['Order is already cancelled.'],
                ]);
            }

            if (in_array($order->status, ['completed', 'shipped'])) {
                throw ValidationException::withMessages([
                    'order' => ["Orders in '{$order->status}' status cannot be cancelled."],
                ]);
            }

            // Restore product/variant stock
            foreach ($order->items as $item) {
                if ($item->product_variant_id) {
                    ProductVariant::where('id', $item->product_variant_id)->increment('stock', $item->quantity);
                } elseif ($item->product_id) {
                    Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                }
            }

            $order->update([
                'status' => 'cancelled',
                'cancel_reason' => $data['reason'] ?? 'Cancelled by customer',
                'cancelled_at' => now(),
            ]);

            return $order->fresh(['items.product.images', 'items.variant']);
        });
    }

    // ==========================================
    // REVIEWS
    // ==========================================

    public function addReview($company, $user, array $data): ProductReview
    {
        $product = Product::query()
            ->where('company_id', $company->id)
            ->where('id', $data['product_id'])
            ->first();

        if (!$product) {
            throw ValidationException::withMessages([
                'product_id' => ['Product not found in this store.'],
            ]);
        }

        $review = ProductReview::updateOrCreate(
            [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'product_id' => $product->id,
            ],
            [
                'rating' => (int) $data['rating'],
                'review' => $data['review'] ?? null,
                'is_approved' => true,
            ]
        );

        return $review->load(['user:id,name', 'product:id,title,slug']);
    }

    public function deleteReview($company, $user, array $data = [], $reviewId = null): bool
    {
        $targetId = $reviewId ?? $data['review_id'] ?? $data['id'] ?? null;
        $productId = $data['product_id'] ?? null;

        $query = ProductReview::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id);

        if ($targetId) {
            $query->where('id', $targetId);
        } elseif ($productId) {
            $query->where('product_id', $productId);
        } else {
            throw ValidationException::withMessages([
                'review_id' => ['Please specify review_id or product_id to delete.'],
            ]);
        }

        $deleted = $query->delete();

        if (!$deleted) {
            throw new NotFoundHttpException('Review not found.');
        }

        return true;
    }

    public function getProductReviews($company, $productId, array $filters = [])
    {
        $query = ProductReview::query()
            ->where('company_id', $company->id)
            ->where('product_id', $productId)
            ->where('is_approved', true)
            ->with('user:id,name')
            ->latest();

        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 100);

        return $query->paginate($perPage);
    }
}
