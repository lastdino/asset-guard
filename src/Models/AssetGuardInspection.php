<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AssetGuardInspection extends Model
{
    protected $fillable = [
        'asset_id', 'performed_by_user_id', 'performed_at', 'status', 'checklist_id'
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
            'status' => 'string',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AssetGuardAsset::class, 'asset_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'performed_by_user_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(AssetGuardInspectionItemResult::class, 'inspection_id');
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(AssetGuardInspectionChecklist::class, 'checklist_id');
    }

    public function inspectors(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'asset_guard_inspection_user',
            'inspection_id',
            'user_id'
        )
            ->withPivot(['role', 'signed_at'])
            ->withTimestamps();
    }
}
