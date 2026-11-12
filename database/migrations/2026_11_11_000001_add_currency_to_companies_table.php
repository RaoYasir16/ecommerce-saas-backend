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
        if (!Schema::hasColumn('companies', 'currency')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('currency', 10)->default('PKR')->after('address');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('companies', 'currency')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
    }
};

