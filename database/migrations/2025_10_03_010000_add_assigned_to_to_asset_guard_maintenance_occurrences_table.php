<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('asset_guard_maintenance_occurrences', function (Blueprint $table): void {
            $table->foreignId('assigned_to')->nullable()->after('asset_id')->constrained('users')->nullOnDelete();
        });

        // Backfill: copy plan.assigned_to to occurrences where missing (portable across SQLite/MySQL)
        $rows = DB::table('asset_guard_maintenance_occurrences as o')
            ->join('asset_guard_maintenance_plans as p', 'p.id', '=', 'o.maintenance_plan_id')
            ->whereNull('o.assigned_to')
            ->whereNotNull('p.assigned_to')
            ->select(['o.id as oid', 'p.assigned_to as pid'])
            ->get();
        foreach ($rows as $row) {
            DB::table('asset_guard_maintenance_occurrences')
                ->where('id', $row->oid)
                ->update(['assigned_to' => $row->pid]);
        }
    }

    public function down(): void
    {
        Schema::table('asset_guard_maintenance_occurrences', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_to');
        });
    }
};
