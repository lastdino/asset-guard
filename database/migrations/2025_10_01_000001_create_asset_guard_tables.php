<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Locations (master)
        Schema::create('asset_guard_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('asset_guard_locations')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        // 2) Assets
        Schema::create('asset_guard_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('serial_no')->nullable()->index();
            $table->string('fixed_asset_no')->nullable()->index();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('asset_guard_locations')->nullOnDelete();
            $table->enum('status', ['Active', 'Inactive', 'UnderMaintenance', 'Retired'])->default('Active')->index();
            $table->date('installed_at')->nullable();
            $table->string('manufacturer')->nullable();
            $table->text('spec')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('asset_guard_assets')->nullOnDelete();
            $table->enum('type', ['Equipment', 'Instrument', 'Electronic', 'Accessory'])->default('Equipment')->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        // 3) Inspection checklists
        Schema::create('asset_guard_inspection_checklists', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('applies_to')->default('Asset');
            $table->foreignId('asset_id')->nullable()->constrained('asset_guard_assets')->cascadeOnDelete();
            $table->string('asset_type')->nullable();
            $table->boolean('active')->default(true);
            // Frequency moved to checklist level (nullable defaults handled at app level)
            $table->string('frequency_unit', 32)->default('Monthly');
            $table->unsignedSmallInteger('frequency_value')->default(1);
            $table->timestamps();
        });

        // 4) Inspection checklist items
        Schema::create('asset_guard_inspection_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checklist_id')->constrained('asset_guard_inspection_checklists')->cascadeOnDelete();
            $table->string('name');
            $table->string('method')->default('text'); // text|number|select|boolean
            $table->json('pass_condition')->nullable();
            $table->decimal('min_value', 15, 4)->nullable();
            $table->decimal('max_value', 15, 4)->nullable();
            // Deprecated per-item frequency kept nullable for backward compatibility
            $table->string('frequency_unit', 32)->nullable();
            $table->unsignedSmallInteger('frequency_value')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // 5) Inspections
        Schema::create('asset_guard_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('asset_guard_assets')->cascadeOnDelete();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');
            $table->foreignId('checklist_id')->nullable()->constrained('asset_guard_inspection_checklists')->nullOnDelete();
            $table->string('status', 20)->default('Draft');
            $table->timestamps();
        });

        // 6) Inspection item results
        Schema::create('asset_guard_inspection_item_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->constrained('asset_guard_inspections')->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained('asset_guard_inspection_checklist_items')->cascadeOnDelete();
            $table->string('result')->default('Pass'); // Pass|Fail|N/A
            $table->string('value')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_draft')->default(true);
            $table->timestamps();
        });

        // 7) Inspection assistants pivot
        Schema::create('asset_guard_inspection_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->constrained('asset_guard_inspections')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('Assistant');
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
            $table->unique(['inspection_id', 'user_id']);
        });

        // 8) Maintenance plans
        Schema::create('asset_guard_maintenance_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('asset_guard_assets')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('scheduled_at');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('timezone')->default(config('app.timezone'));
            $table->unsignedTinyInteger('lead_time_days')->default(3);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('Scheduled');
            $table->foreignId('checklist_id')->nullable()->constrained('asset_guard_inspection_checklists')->nullOnDelete();
            $table->string('trigger_type', 20)->nullable();
            $table->boolean('require_before_activation')->default(false);
            $table->timestamps();
        });

        // 10) Incidents
        Schema::create('asset_guard_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('asset_guard_assets')->cascadeOnDelete();
            $table->timestamp('occurred_at');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignee_name')->nullable();
            $table->text('event');
            $table->text('actions')->nullable();
            $table->enum('status', ['Waiting', 'InProgress', 'Completed'])->default('Waiting');
            $table->string('severity', 20)->default('Medium');
            $table->timestamp('completed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['asset_id', 'occurred_at']);
            $table->index(['status']);
            $table->index(['severity']);
        });
    }

    public function down(): void
    {
        // Drop child tables first due to FKs
        Schema::dropIfExists('asset_guard_inspection_user');
        Schema::dropIfExists('asset_guard_inspection_item_results');
        Schema::dropIfExists('asset_guard_inspections');
        Schema::dropIfExists('asset_guard_inspection_checklist_items');
        Schema::dropIfExists('asset_guard_inspection_checklists');
        Schema::dropIfExists('asset_guard_maintenance_plans');
        Schema::dropIfExists('asset_guard_incidents');
        Schema::dropIfExists('asset_guard_assets');
        Schema::dropIfExists('asset_guard_locations');
    }
};
