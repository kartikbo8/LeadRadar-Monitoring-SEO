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
        Schema::create('monitored_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('website_url');
            $table->string('host', 253);
            $table->text('verification_token');
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'host']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitored_sites');
    }
};
