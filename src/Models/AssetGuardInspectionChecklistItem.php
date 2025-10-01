<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AssetGuardInspectionChecklistItem extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'checklist_id', 'name', 'method', 'pass_condition', 'min_value', 'max_value', 'frequency_unit', 'frequency_value', 'sort_order'
    ];

    protected function casts(): array
    {
        return [
            'pass_condition' => 'array',
            'min_value' => 'decimal:4',
            'max_value' => 'decimal:4',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('reference_photos')
            ->useDisk('local')
            ->withResponsiveImages();
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(AssetGuardInspectionChecklist::class, 'checklist_id');
    }

}
