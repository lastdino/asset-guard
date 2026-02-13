<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_guard_inspection_checklists', function (Blueprint $table): void {
            if (! Schema::hasColumn('asset_guard_inspection_checklists', 'require_before_activation')) {
                $table->boolean('require_before_activation')->default(false)->after('active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_guard_inspection_checklists', function (Blueprint $table): void {
            if (Schema::hasColumn('asset_guard_inspection_checklists', 'require_before_activation')) {
                $table->dropColumn('require_before_activation');
            }
        });
    }
};
