<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('store_settings', 'google_map_url')) {
                $table->text('google_map_url')->nullable()->after('country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            if (Schema::hasColumn('store_settings', 'google_map_url')) {
                $table->dropColumn('google_map_url');
            }
        });
    }
};

