<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AssetGuardInspectionItemResult extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'inspection_id', 'checklist_item_id', 'result', 'value', 'note', 'is_draft',
    ];

    protected function casts(): array
    {
        return [
            'is_draft' => 'boolean',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(AssetGuardInspection::class, 'inspection_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AssetGuardInspectionChecklistItem::class, 'checklist_item_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk(config('filesystems.default'))
            ->acceptsMimeTypes(['image/*', 'application/pdf'])
            ->withResponsiveImages();
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(320)
            ->height(320)
            ->performOnCollections('attachments')
            ->nonQueued();
    }
}
