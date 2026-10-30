<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('referral_code', 30)->nullable()->unique()->after('status');
            $table->foreignId('referred_by')->nullable()->after('referral_code')->constrained('companies')->nullOnDelete();
        });

        // Generate unique referral codes for any existing companies
        $companies = DB::table('companies')->whereNull('referral_code')->get();
        foreach ($companies as $comp) {
            $code = 'REF-' . strtoupper(Str::random(6));
            while (DB::table('companies')->where('referral_code', $code)->exists()) {
                $code = 'REF-' . strtoupper(Str::random(6));
            }
            DB::table('companies')->where('id', $comp->id)->update(['referral_code' => $code]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['referred_by']);
            $table->dropColumn(['referred_by', 'referral_code']);
        });
    }
};
