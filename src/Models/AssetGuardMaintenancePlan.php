<?php

namespace Lastdino\AssetGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetGuardMaintenancePlan extends Model
{
    protected $fillable = [
        'asset_id',
        'checklist_id',
        'title',
        'description',
        'trigger_type',
        'require_before_activation',
        'start_date',
        'end_date',
        'timezone',
        'lead_time_days',
        'assigned_to',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'lead_time_days' => 'integer',
            'require_before_activation' => 'boolean',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AssetGuardAsset::class, 'asset_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(AssetGuardMaintenanceOccurrence::class, 'maintenance_plan_id');
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(AssetGuardInspectionChecklist::class, 'checklist_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }
}
