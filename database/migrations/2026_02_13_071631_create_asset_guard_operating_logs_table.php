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
        Schema::create('asset_guard_operating_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('asset_guard_assets')->cascadeOnDelete();
            $table->string('status'); // 'running', 'stopped'
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->string('source')->default('manual'); // 'manual', 'plc', 'system'
            $table->timestamps();

            $table->index(['asset_id', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_guard_operating_logs');
    }
};
