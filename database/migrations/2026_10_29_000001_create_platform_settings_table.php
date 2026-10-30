<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('referral_commission_percentage', 5, 2)->default(5.00);
            $table->boolean('is_referral_enabled')->default(true);
            $table->string('currency', 10)->default('PKR');
            $table->json('additional_settings')->nullable();
            $table->timestamps();
        });

        // Insert default setting record with 5% referral commission
        DB::table('platform_settings')->insert([
            'referral_commission_percentage' => 5.00,
            'is_referral_enabled' => true,
            'currency' => 'PKR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
