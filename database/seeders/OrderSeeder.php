<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StoreVisit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();

        if ($companies->isEmpty()) {
            return;
        }

        $sampleCustomers = [
            ['name' => 'Hamza Malik', 'email' => 'hamza.m@gmail.com', 'city' => 'Lahore', 'phone' => '+92 300 1234567'],
            ['name' => 'Sara Ahmed', 'email' => 'sara.design@outlook.com', 'city' => 'Karachi', 'phone' => '+92 321 7654321'],
            ['name' => 'Zainab Tariq', 'email' => 'zainab.t@gmail.com', 'city' => 'Islamabad', 'phone' => '+92 333 9988776'],
            ['name' => 'Bilal Sheikh', 'email' => 'bilal.s@yahoo.com', 'city' => 'Faisalabad', 'phone' => '+92 345 4433221'],
            ['name' => 'Ayesha Noor', 'email' => 'ayesha.n@gmail.com', 'city' => 'Rawalpindi', 'phone' => '+92 302 5566778'],
            ['name' => 'Usman Ghani', 'email' => 'usman.g@gmail.com', 'city' => 'Multan', 'phone' => '+92 312 9900112'],
        ];

        foreach ($companies as $company) {
            // Find or create products for the company so orders can reference them
            $products = Product::where('company_id', $company->id)->get();

            if ($products->isEmpty()) {
                $p1 = Product::create([
                    'company_id' => $company->id,
                    'title' => 'Minimalist Oversized Bomber Jacket',
                    'slug' => 'minimalist-oversized-bomber-jacket-' . Str::random(4),
                    'description' => 'Premium streetwear outerwear crafted for style and warmth.',
                    'regular_price' => 129.99,
                    'sale_price' => 119.99,
                    'stock' => 45,
                    'has_variants' => false,
                    'is_available' => true,
                ]);

                $p2 = Product::create([
                    'company_id' => $company->id,
                    'title' => 'Heavyweight Boxy Cotton Tee',
                    'slug' => 'heavyweight-boxy-cotton-tee-' . Str::random(4),
                    'description' => '240 GSM organic cotton t-shirt with modern boxy drape.',
                    'regular_price' => 44.00,
                    'sale_price' => 38.00,
                    'stock' => 80,
                    'has_variants' => false,
                    'is_available' => true,
                ]);

                $p3 = Product::create([
                    'company_id' => $company->id,
                    'title' => 'Wide-Leg Relaxed Cargo Pants',
                    'slug' => 'wide-leg-relaxed-cargo-pants-' . Str::random(4),
                    'description' => 'Durable ripstop cargo trousers with deep tactical utility pockets.',
                    'regular_price' => 89.50,
                    'sale_price' => 79.50,
                    'stock' => 30,
                    'has_variants' => false,
                    'is_available' => true,
                ]);

                $products = collect([$p1, $p2, $p3]);
            }

            // Only seed orders if company has none
            if (Order::where('company_id', $company->id)->count() === 0) {
                $now = Carbon::now();
                $statuses = ['pending', 'processing', 'shipped', 'delivered', 'delivered'];
                $paymentMethods = ['cod', 'stripe', 'jazzcash', 'card', 'cod'];

                foreach ($sampleCustomers as $index => $cust) {
                    $prod = $products[$index % $products->count()];
                    $qty = ($index % 2) + 1;
                    $itemPrice = (float) ($prod->sale_price ?? $prod->regular_price);
                    $subtotal = round($itemPrice * $qty, 2);
                    $total = $subtotal;

                    $status = $statuses[$index % count($statuses)];
                    $paymentStatus = in_array($status, ['processing', 'shipped', 'delivered']) ? 'paid' : 'pending';
                    $daysAgo = $index; // spread over 0, 1, 2, 3, 4, 5 days ago
                    $createdAt = $now->copy()->subDays($daysAgo)->subHours($index * 2 + 1);

                    $order = Order::create([
                        'company_id' => $company->id,
                        'user_id' => null,
                        'order_number' => 'ORD-' . (9420 - $index) . '-' . strtoupper(Str::random(4)),
                        'status' => $status,
                        'payment_status' => $paymentStatus,
                        'payment_method' => $paymentMethods[$index % count($paymentMethods)],
                        'customer_name' => $cust['name'],
                        'customer_email' => $cust['email'],
                        'customer_phone' => $cust['phone'],
                        'shipping_address' => 'Street ' . ($index + 1) . ', Sector B, Block 4',
                        'city' => $cust['city'],
                        'postal_code' => '54000',
                        'subtotal' => $subtotal,
                        'shipping_fee' => 0.00,
                        'tax' => 0.00,
                        'discount' => 0.00,
                        'total_amount' => $total,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $prod->id,
                        'product_title' => $prod->title,
                        'variant_sku' => 'SKU-' . $prod->id,
                        'price' => $itemPrice,
                        'quantity' => $qty,
                        'subtotal' => $subtotal,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }
            }

            // Seed some visits if none
            if (StoreVisit::where('company_id', $company->id)->count() === 0) {
                $now = Carbon::now();
                for ($d = 0; $d < 7; $d++) {
                    $visitsForDay = rand(15, 45);
                    for ($v = 0; $v < $visitsForDay; $v++) {
                        StoreVisit::create([
                            'company_id' => $company->id,
                            'ip_address' => '192.168.1.' . rand(10, 250),
                            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                            'referer' => 'https://google.com',
                            'visited_at' => $now->copy()->subDays($d)->subMinutes(rand(10, 1400)),
                        ]);
                    }
                }
            }
        }
    }
}
