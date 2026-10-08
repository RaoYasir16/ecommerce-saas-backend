<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('company_id')->nullable();
            
            // Branding & Color Scheme
            $table->string('primary_color')->default('#1E3A8A'); // Primary brand color
            $table->string('secondary_color')->default('#3B82F6');
            $table->string('accent_color')->default('#10B981');
            $table->string('header_bg_color')->default('#FFFFFF');
            $table->string('footer_bg_color')->default('#1F2937');

            // Home Page Banners (Min 1, Max 5 images path / metadata JSON)
            $table->json('banners')->nullable(); 

            // Terms & Conditions Page Configuration
            $table->boolean('use_custom_terms')->default(false); // false = default platform terms, true = merchant terms
            $table->longText('terms_and_conditions')->nullable();

            // Contact & Social Links
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('tiktok_url')->nullable();

            // Announcement Bar (Optional bonus for e-commerce)
            $table->boolean('show_announcement')->default(false);
            $table->string('announcement_text')->nullable();

            $table->string('footer_text')->nullable();

            $table->timestamps();

            // Ek tenant ki sirf ek hi settings row honi chahiye
            $table->unique('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
