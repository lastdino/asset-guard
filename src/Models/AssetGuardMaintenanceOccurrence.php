<?php

namespace Lastdino\AssetGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetGuardMaintenanceOccurrence extends Model
{
    protected $fillable = [
        'maintenance_plan_id',
        'asset_id',
        'assigned_to',
        'planned_at',
        'due_at',
        'completed_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'planned_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AssetGuardMaintenancePlan::class, 'maintenance_plan_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AssetGuardAsset::class, 'asset_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }
}
