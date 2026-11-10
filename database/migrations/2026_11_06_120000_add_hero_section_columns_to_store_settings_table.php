<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('store_settings', 'hero_type')) {
                $table->string('hero_type', 50)->default('slider')->after('banners');
            }
            if (!Schema::hasColumn('store_settings', 'hero_badge')) {
                $table->string('hero_badge')->nullable()->after('hero_type');
            }
            if (!Schema::hasColumn('store_settings', 'hero_title')) {
                $table->string('hero_title')->nullable()->after('hero_badge');
            }
            if (!Schema::hasColumn('store_settings', 'hero_subtitle')) {
                $table->text('hero_subtitle')->nullable()->after('hero_title');
            }
            if (!Schema::hasColumn('store_settings', 'hero_image')) {
                $table->string('hero_image')->nullable()->after('hero_subtitle');
            }
            if (!Schema::hasColumn('store_settings', 'hero_button_text')) {
                $table->string('hero_button_text')->nullable()->after('hero_image');
            }
            if (!Schema::hasColumn('store_settings', 'hero_button_link')) {
                $table->string('hero_button_link')->nullable()->after('hero_button_text');
            }
            if (!Schema::hasColumn('store_settings', 'hero_features')) {
                $table->longText('hero_features')->nullable()->after('hero_button_link');
            }
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $columns = [
                'hero_type',
                'hero_badge',
                'hero_title',
                'hero_subtitle',
                'hero_image',
                'hero_button_text',
                'hero_button_link',
                'hero_features',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('store_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

