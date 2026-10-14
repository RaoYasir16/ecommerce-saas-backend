<?php

namespace App\Http\Controllers\Api\StoreCustomer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AddFavoriteRequest;
use App\Http\Requests\Customer\AddReviewRequest;
use App\Http\Requests\Customer\AddToCartRequest;
use App\Http\Requests\Customer\CancelOrderRequest;
use App\Http\Requests\Customer\PlaceOrderRequest;
use App\Http\Requests\Customer\RemoveFavoriteRequest;
use App\Http\Requests\Customer\RemoveFromCartRequest;
use App\Services\StoreCustomer\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerStoreController extends Controller
{
    public function __construct(
        protected StoreService $storeService
    ) {}

    // ==========================================
    // FAVORITES / WISHLIST
    // ==========================================

    public function addFavorite(AddFavoriteRequest $request): JsonResponse
    {
        $company = $request->attributes->get('storeCompany');
        $user = $request->user();

        $favorite = $this->storeService->addFavorite($company, $user, $request->validated());

        return $this->apiResponse(true, 'Product added to favorites successfully.', $favorite, 201);
    }

    public function getFavorites(Request $request): JsonResponse
    {
        $company = $request->attributes->get('storeCompany');
        $user = $request->user();

        $favorites = $this->storeService->getFavorites($company, $user, $request->only('per_page'));

        return $this->apiResponse(true, 'Favorites retrieved successfully.', $favorites);
    }

    public function removeFavorite(RemoveFavoriteRequest $request, $id = null): JsonResponse
    {
        $company = $request->attributes->get('storeCompany');
        $user = $request->user();

        $data = $request->validated();
        if ($id !== null) {
            $data['id'] = $id;
        }

        $this->storeService->removeFavorite($company, $user, $data);

        return $this->apiResponse(true, 'Product removed from favorites successfully.');
    }

    // ==========================================
    // CART
    // ==========================================

    public function addToCart(AddToCartRequest $request): JsonResponse
    {
        $company = $request->attributes->get('storeCompany');
        $user = $request->user();

        $cartItem = $this->storeService->addToCart($company, $user, $request->validated());

        return $this->apiResponse(true, 'Product added to cart successfully.', $cartItem, 201);
    }

    public function getCart(Request $request): JsonResponse
    {
        $company = $request->attributes->get('storeCompany');
        $user = $request->user();

        $cart = $this->storeService->getCart($company, $user);

        return $this->apiResponse(true, 'Cart retrieved successfully.', $cart);
    }

    public function removeFromCart(RemoveFromCartRequest $request, $id = null): JsonResponse
    {
        $company = $request->attributes->get('storeCompany');
        $user = $request->user();

        $data = $request->validated();
        if ($id !== null) {
            $data['id'] = $id;
        }

        $this->storeService->removeFromCart($company, $user, $data, $id);

        return $this->apiResponse(true, 'Item removed from cart successfully.');
    }

    public function updateCartQuantity(Request $request): JsonResponse
    {
        $request->validate([
            'cart_item_id' => ['nullable', 'integer'],
            'id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
            'product_variant_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        $company = $request->attributes->get('storeCompany');
        $user = $request->user();

        $updated = $this->storeService->updateCartQuantity($company, $user, $request->all());

        return $this->apiResponse(true, 'Cart quantity updated successfully.', $updated);
    }

    public function clearCart(Request $request): JsonResponse
    {
        $company = $request->attributes->get('storeCompany');
        $user = $request->user();

        $this->storeService->clearCart($company, $user);

        return $this->apiResponse(true, 'Cart cleared successfully.');
    }

    // ==========================================
    // ORDERS
    // ==========================================

    public function placeOrder(PlaceOrderRequest $request): JsonResponse
    {
        $company = $request->attributes->get('storeCompany');
        $user = $request->user();

        $order = $this->storeService->placeOrder($company, $user, $request->validated());

        return $this->apiResponse(true, 'Order placed successfully.', $order, 201);
    }

    public function getOrders(Request $request): JsonResponse
    {
        $company = $request->attributes->get('storeCompany');
        $user = $request->user();

        $orders = $this->storeService->getOrders($company, $user, $request->only(['status', 'per_page']));

        return $this->apiResponse(true, 'Orders retrieved successfully.', $orders);
    }

    public function showOrder(Request $request, $id): JsonResponse
    {
        $company = $request->attributes->get('storeCompany');
        $user = $request->user();

        $order = $this->storeService->getOrder($company, $user, $id);

        return $this->apiResponse(true, 'Order details retrieved successfully.', $order);
    }

    public function cancelOrder(CancelOrderRequest $request, $id = null): JsonResponse
    {
        $company = $request->attributes->get('storeCompany');
        $user = $request->user();

        $data = $request->validated();
        if ($id !== null) {
            $data['id'] = $id;
        }

        $order = $this->storeService->cancelOrder($company, $user, $data, $id);

        return $this->apiResponse(true, 'Order cancelled successfully.', $order);
    }

    // ==========================================
    // REVIEWS
    // ==========================================

    public function addReview(AddReviewRequest $request): JsonResponse
    {
        $company = $request->attributes->get('storeCompany');
        $user = $request->user();

        $review = $this->storeService->addReview($company, $user, $request->validated());

        return $this->apiResponse(true, 'Review submitted successfully.', $review, 201);
    }

    public function deleteReview(Request $request, $id = null): JsonResponse
    {
        $request->validate([
            'review_id' => ['nullable', 'integer'],
            'id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
        ]);

        $company = $request->attributes->get('storeCompany');
        $user = $request->user();

        $data = $request->all();
        if ($id !== null) {
            $data['id'] = $id;
        }

        $this->storeService->deleteReview($company, $user, $data, $id);

        return $this->apiResponse(true, 'Review deleted successfully.');
    }

    public function getProductReviews(Request $request, $productId): JsonResponse
    {
        $company = $request->attributes->get('storeCompany');

        $reviews = $this->storeService->getProductReviews($company, $productId, $request->only('per_page'));

        return $this->apiResponse(true, 'Reviews retrieved successfully.', $reviews);
    }
}
