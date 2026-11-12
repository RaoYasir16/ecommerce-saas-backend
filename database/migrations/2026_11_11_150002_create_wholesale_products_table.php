<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wholesale_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('wholesale_categories')
                ->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('sku', 100)->unique();
            $table->text('description')->nullable();
            $table->decimal('wholesale_price', 12, 2);
            $table->decimal('retail_price', 12, 2)->nullable();
            $table->string('currency', 10)->default('PKR');
            $table->integer('moq')->default(1);
            $table->integer('stock')->default(0);
            $table->string('image')->nullable();
            $table->json('colors')->nullable();
            $table->json('sizes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('in_stock')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wholesale_products');
    }
};

