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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Store Name (e.g., Noor Mega Store)
            $table->string('subdomain')->unique(); // noormegastore
            $table->string('logo')->nullable();
            $table->string('whatsapp_number'); // +923001234567
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            // Trial Lifecycle (1 Month Free)
            $table->timestamp('trial_ends_at')->nullable();
            $table->enum('status', ['trial', 'active', 'suspended', 'expired'])->default('trial');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
