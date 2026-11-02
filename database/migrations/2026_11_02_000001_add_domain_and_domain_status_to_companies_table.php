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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('domain')->nullable()->unique()->after('subdomain');
            $table->enum('domain_status', ['pending', 'active', 'failed'])->default('pending')->after('domain');
        });

        // Backfill existing companies if any
        $baseDomain = config('services.hostinger.domain', 'noorwear.pk');
        $companies = DB::table('companies')->whereNull('domain')->get();
        foreach ($companies as $comp) {
            if (!empty($comp->subdomain)) {
                DB::table('companies')->where('id', $comp->id)->update([
                    'domain' => "{$comp->subdomain}.{$baseDomain}",
                    'domain_status' => 'active',
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['domain']);
            $table->dropColumn(['domain', 'domain_status']);
        });
    }
};
