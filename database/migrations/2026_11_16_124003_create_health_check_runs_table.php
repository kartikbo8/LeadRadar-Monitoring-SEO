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
        Schema::create('health_check_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitored_site_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->json('results')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->date('scheduled_for')->nullable();
            $table->timestamps();

            $table->index(['monitored_site_id', 'scanned_at']);
            $table->unique(['monitored_site_id', 'scheduled_for']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_check_runs');
    }
};
