<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use App\Models\WholesaleCategory;
use App\Models\WholesaleProduct;
use Illuminate\Database\Seeder;

class WholesaleSeeder extends Seeder
{
    public function run(): void
    {
        $currency = PlatformSetting::first()?->currency ?: 'PKR';

        $categories = [
            ['slug' => 'apparel', 'name' => 'Apparel & Garments', 'sort_order' => 1, 'description' => 'Bulk cotton tees, basics, and ready-to-brand apparel.'],
            ['slug' => 'hoodies', 'name' => 'Hoodies & Fleece', 'sort_order' => 2, 'description' => 'Heavyweight fleece hoodies and crewneck sweatshirts.'],
            ['slug' => 'denim', 'name' => 'Denim & Jeans', 'sort_order' => 3, 'description' => 'Export quality denim jeans and cargo utility trousers.'],
            ['slug' => 'activewear', 'name' => 'Activewear & Gym', 'sort_order' => 4, 'description' => 'Compression gym sets and moisture-wicking performance gear.'],
            ['slug' => 'accessories', 'name' => 'Packaging & Accessories', 'sort_order' => 5, 'description' => 'Branded polymailers, boxes, and eCommerce fulfillment supplies.'],
            ['slug' => 'footwear', 'name' => 'Footwear & Shoes', 'sort_order' => 6, 'description' => 'Wholesale canvas sneakers and retail-boxed footwear.'],
        ];

        $categoryMap = [];
        foreach ($categories as $cat) {
            $record = WholesaleCategory::firstOrCreate(
                ['slug' => $cat['slug']],
                [
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'sort_order' => $cat['sort_order'],
                    'is_active' => true,
                ]
            );
            $categoryMap[$cat['slug']] = $record->id;
        }

        $products = [
            [
                'category_slug' => 'hoodies',
                'slug' => 'oversized-drop-shoulder-heavyweight-hoodie-380-gsm',
                'title' => 'Oversized Drop-Shoulder Heavyweight Hoodie (380 GSM)',
                'sku' => 'WHOLE-HD-380',
                'wholesale_price' => 1150,
                'retail_price' => 2800,
                'moq' => 20,
                'stock' => 650,
                'image' => 'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?auto=format&fit=crop&w=700&q=80',
                'description' => 'Heavyweight 100% French Terry cotton hoodie with brushed fleece interior. Pre-shrunk, double-stitched seams, and premium metal aglets.',
                'colors' => ['Onyx Black', 'Stone Grey', 'Sage Green', 'Espresso Brown'],
                'sizes' => ['S', 'M', 'L', 'XL'],
            ],
            [
                'category_slug' => 'apparel',
                'slug' => 'premium-240-gsm-combed-cotton-plain-t-shirt',
                'title' => 'Premium 240 GSM Combed Cotton Plain T-Shirt',
                'sku' => 'WHOLE-TEE-240',
                'wholesale_price' => 480,
                'retail_price' => 1200,
                'moq' => 50,
                'stock' => 1850,
                'image' => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?auto=format&fit=crop&w=700&q=80',
                'description' => 'Bio-washed, 100% combed ring-spun cotton tee with ribbed lycra collar for screen printing or direct brand tagging.',
                'colors' => ['White', 'Jet Black', 'Navy Blue', 'Off White', 'Khaki'],
                'sizes' => ['M', 'L', 'XL', 'XXL'],
            ],
            [
                'category_slug' => 'denim',
                'slug' => 'export-quality-stretch-slim-tapered-denim-jeans-13-5-oz',
                'title' => 'Export Quality Stretch Slim-Tapered Denim Jeans (13.5 oz)',
                'sku' => 'WHOLE-DNM-135',
                'wholesale_price' => 1350,
                'retail_price' => 3200,
                'moq' => 25,
                'stock' => 420,
                'image' => 'https://images.unsplash.com/photo-1542272604-780c96856592?auto=format&fit=crop&w=700&q=80',
                'description' => 'Authentic 13.5 oz Turkish slub denim with 2% elastane, YKK antique brass zippers, and reinforced rivets.',
                'colors' => ['Vintage Indigo', 'Raw Black', 'Medium Washed Blue'],
                'sizes' => ['30', '32', '34', '36'],
            ],
            [
                'category_slug' => 'activewear',
                'slug' => 'seamless-ribbed-compression-gym-and-yoga-set',
                'title' => 'Seamless Ribbed Compression Gym & Yoga Set (Top + Leggings)',
                'sku' => 'WHOLE-ACT-SET',
                'wholesale_price' => 980,
                'retail_price' => 2400,
                'moq' => 30,
                'stock' => 580,
                'image' => 'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=700&q=80',
                'description' => 'High-stretch nylon-spandex blend engineered for moisture wicking and four-way stretch.',
                'colors' => ['Dusty Rose', 'Charcoal', 'Forest Green', 'Sky Blue'],
                'sizes' => ['S', 'M', 'L'],
            ],
            [
                'category_slug' => 'accessories',
                'slug' => 'custom-branded-matte-black-polymailers-pack-of-100',
                'title' => 'Custom Branded Matte Black Polymailers (Pack of 100 pcs)',
                'sku' => 'WHOLE-PKG-100',
                'wholesale_price' => 1400,
                'retail_price' => 2500,
                'moq' => 5,
                'stock' => 320,
                'image' => 'https://images.unsplash.com/photo-1589939705384-5185137a7f0f?auto=format&fit=crop&w=700&q=80',
                'description' => 'Tear-resistant, waterproof 3-layer co-extruded courier bags with tamper-evident self-seal adhesive.',
                'colors' => ['Matte Black', 'Frosted White'],
                'sizes' => ['10x13 inch', '12x15 inch'],
            ],
            [
                'category_slug' => 'hoodies',
                'slug' => 'relaxed-fit-terry-cotton-crewneck-sweatshirt-320-gsm',
                'title' => 'Relaxed-Fit Terry Cotton Crewneck Sweatshirt (320 GSM)',
                'sku' => 'WHOLE-CRW-320',
                'wholesale_price' => 890,
                'retail_price' => 2200,
                'moq' => 25,
                'stock' => 810,
                'image' => 'https://images.unsplash.com/photo-1620799140408-edc6dcb6d633?auto=format&fit=crop&w=700&q=80',
                'description' => 'Classic minimalist crewneck pullover crafted from breathable unbrushed looped French Terry.',
                'colors' => ['Heather Grey', 'Charcoal', 'Sand Beige', 'Maroon'],
                'sizes' => ['M', 'L', 'XL'],
            ],
            [
                'category_slug' => 'denim',
                'slug' => 'utility-multi-pocket-cargo-trousers-water-repellent-cotton',
                'title' => 'Utility Multi-Pocket Cargo Trousers (Water-Repellent Cotton)',
                'sku' => 'WHOLE-CRG-UTL',
                'wholesale_price' => 1250,
                'retail_price' => 2900,
                'moq' => 20,
                'stock' => 490,
                'image' => 'https://images.unsplash.com/photo-1624378439575-d8705ad7ae80?auto=format&fit=crop&w=700&q=80',
                'description' => 'Heavy cotton twill cargo pants with 6 reinforced utility pockets, elasticated cuffs, and adjustable drawstring waistband.',
                'colors' => ['Military Green', 'Midnight Black', 'Desert Tan'],
                'sizes' => ['30', '32', '34', '36'],
            ],
            [
                'category_slug' => 'footwear',
                'slug' => 'minimalist-high-top-canvas-streetwear-sneakers',
                'title' => 'Minimalist High-Top Canvas Streetwear Sneakers (Vulcanized)',
                'sku' => 'WHOLE-SH-CANV',
                'wholesale_price' => 1650,
                'retail_price' => 3800,
                'moq' => 15,
                'stock' => 260,
                'image' => 'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=700&q=80',
                'description' => '12 oz heavy canvas upper with vulcanized non-slip rubber waffle outsole and memory foam cushioned insole.',
                'colors' => ['Classic White', 'Triple Black', 'Navy Grey'],
                'sizes' => ['40', '41', '42', '43', '44'],
            ],
        ];

        foreach ($products as $prod) {
            $categoryId = $categoryMap[$prod['category_slug']] ?? reset($categoryMap);
            WholesaleProduct::firstOrCreate(
                ['sku' => $prod['sku']],
                [
                    'category_id' => $categoryId,
                    'title' => $prod['title'],
                    'slug' => $prod['slug'],
                    'description' => $prod['description'],
                    'wholesale_price' => $prod['wholesale_price'],
                    'retail_price' => $prod['retail_price'],
                    'currency' => $currency,
                    'moq' => $prod['moq'],
                    'stock' => $prod['stock'],
                    'image' => $prod['image'],
                    'colors' => $prod['colors'],
                    'sizes' => $prod['sizes'],
                    'is_active' => true,
                    'in_stock' => true,
                ]
            );
        }
    }
}

