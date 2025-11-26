<?php

namespace Lastdino\AssetGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetGuardMaintenancePlan extends Model
{
    protected $fillable = [
        'id',
        'asset_id',
        'checklist_id',
        'title',
        'description',
        'scheduled_at',
        'due_at',
        'completed_at',
        'timezone',
        'lead_time_days',
        'assigned_to',
        'status',
        'trigger_type',
        'require_before_activation',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if ($model->scheduled_at === null) {
                $model->scheduled_at = now()->toDateString();
            }
            if ($model->timezone === null) {
                $model->timezone = config('app.timezone');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'immutable_date',
            'due_at' => 'immutable_date',
            'completed_at' => 'immutable_date',
            'lead_time_days' => 'integer',
            'require_before_activation' => 'boolean',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AssetGuardAsset::class, 'asset_id');
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
