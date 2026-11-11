<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('platform_settings', 'withdrawal_fee_percentage')) {
                $table->decimal('withdrawal_fee_percentage', 5, 2)->default(2.00)->after('referral_commission_percentage');
            }
        });

        DB::table('platform_settings')
            ->whereNull('withdrawal_fee_percentage')
            ->update(['withdrawal_fee_percentage' => 2.00]);
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            if (Schema::hasColumn('platform_settings', 'withdrawal_fee_percentage')) {
                $table->dropColumn('withdrawal_fee_percentage');
            }
        });
    }
};

