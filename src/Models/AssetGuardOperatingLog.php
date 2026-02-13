<?php

namespace Lastdino\AssetGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetGuardOperatingLog extends Model
{
    protected $fillable = [
        'asset_id',
        'status',
        'started_at',
        'ended_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AssetGuardAsset::class, 'asset_id');
    }
}
