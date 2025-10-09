<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asset_guard_asset_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::table('asset_guard_assets', function (Blueprint $table): void {
            if (!Schema::hasColumn('asset_guard_assets', 'asset_type_id')) {
                $table->foreignId('asset_type_id')->nullable()->after('parent_id')
                    ->constrained('asset_guard_asset_types')->nullOnDelete();
            }
        });

        Schema::table('asset_guard_inspection_checklists', function (Blueprint $table): void {
            if (!Schema::hasColumn('asset_guard_inspection_checklists', 'asset_type_id')) {
                $table->foreignId('asset_type_id')->nullable()->after('asset_id')
                    ->constrained('asset_guard_asset_types')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_guard_inspection_checklists', function (Blueprint $table): void {
            if (Schema::hasColumn('asset_guard_inspection_checklists', 'asset_type_id')) {
                $table->dropConstrainedForeignId('asset_type_id');
            }
        });

        Schema::table('asset_guard_assets', function (Blueprint $table): void {
            if (Schema::hasColumn('asset_guard_assets', 'asset_type_id')) {
                $table->dropConstrainedForeignId('asset_type_id');
            }
        });

        Schema::dropIfExists('asset_guard_asset_types');
    }
};
