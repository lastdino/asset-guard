<?php

namespace Lastdino\AssetGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AssetGuardIncident extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'asset_guard_incidents';

    protected $fillable = [
        'asset_id',
        'occurred_at',
        'assignee_id',
        'assignee_name',
        'event',
        'actions',
        'status',
        'severity',
        'completed_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk('local')
            ->withResponsiveImages();
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AssetGuardAsset::class, 'asset_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assignee_id');
    }
}
