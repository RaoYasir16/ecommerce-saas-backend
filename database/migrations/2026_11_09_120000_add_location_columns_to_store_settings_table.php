<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('store_settings', 'city')) {
                $table->string('city', 100)->nullable()->after('support_phone');
            }
            if (!Schema::hasColumn('store_settings', 'province')) {
                $table->string('province', 100)->nullable()->after('city');
            }
            if (!Schema::hasColumn('store_settings', 'country')) {
                $table->string('country', 100)->nullable()->after('province');
            }
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $columns = ['city', 'province', 'country'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('store_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

