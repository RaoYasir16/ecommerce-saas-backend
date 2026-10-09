<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('company_id')->nullable();

            $table->bigInteger('category_id')->nullable();

            $table->string('title');
            $table->string('slug');

            $table->text('description')->nullable();

            $table->decimal('regular_price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();

            $table->integer('stock')->default(0);

            $table->boolean('has_variants')->default(false);

            $table->boolean('is_available')->default(true);

            $table->timestamps();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};