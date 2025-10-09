<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetGuardInspectionChecklist extends Model
{
    protected $fillable = ['name','applies_to','asset_id','asset_type_id','active','frequency_unit','frequency_value','require_before_activation'];

    protected function casts(): array
    {
        return [
            'active' => 'bool',
            'frequency_value' => 'integer',
            'require_before_activation' => 'bool',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AssetGuardAsset::class, 'asset_id');
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetGuardAssetType::class, 'asset_type_id');
    }

    public function scopeForAssetType(Builder $query, int $assetTypeId): Builder
    {
        return $query->where('applies_to', 'asset_type')->where('asset_type_id', $assetTypeId);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssetGuardInspectionChecklistItem::class, 'checklist_id');
    }
}
