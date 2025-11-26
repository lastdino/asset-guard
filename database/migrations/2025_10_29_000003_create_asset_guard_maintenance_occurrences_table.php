<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_guard_maintenance_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('maintenance_plan_id')->index();
            $table->unsignedBigInteger('asset_id')->index();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->dateTime('planned_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('status')->default('Scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_guard_maintenance_occurrences');
    }
};
