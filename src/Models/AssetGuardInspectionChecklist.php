<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetGuardInspectionChecklist extends Model
{
    protected $fillable = ['name','applies_to','asset_id','asset_type','active','frequency_unit','frequency_value'];

    protected function casts(): array
    {
        return [
            'active' => 'bool',
            'frequency_value' => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AssetGuardAsset::class, 'asset_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssetGuardInspectionChecklistItem::class, 'checklist_id');
    }
}
