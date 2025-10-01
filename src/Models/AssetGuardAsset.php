<?php

namespace Lastdino\AssetGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Image\Enums\Fit;

class AssetGuardAsset extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'code',
        'name',
        'serial_no',
        'fixed_asset_no',
        'manager_id',
        'location_id',
        'location',
        'status',
        'installed_at',
        'manufacturer',
        'spec',
        'parent_id',
        'type',
        'meta',
    ];

    public function casts(): array
    {
        return [
            'installed_at' => 'date:Y-m-d',
            'meta' => 'array',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'manager_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(AssetGuardLocation::class, 'location_id');
    }

    public function inspectionChecklists(): HasMany
    {
        return $this->hasMany(AssetGuardInspectionChecklist::class, 'asset_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(AssetGuardInspection::class, 'asset_id')->latest('performed_at');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(AssetGuardIncident::class, 'asset_id')->latest('occurred_at');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk('local')
            ->withResponsiveImages();
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this
            ->addMediaConversion('thumb')
            ->fit(Fit::Contain, 400, 300)
            ->nonQueued();
    }
}
