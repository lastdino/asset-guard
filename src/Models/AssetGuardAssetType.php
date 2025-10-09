<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetGuardAssetType extends Model
{
    protected $fillable = ['name', 'code', 'description', 'sort_order', 'meta'];

    public function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(AssetGuardAsset::class, 'asset_type_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(AssetGuardInspectionChecklist::class, 'asset_type_id')
            ->where('applies_to', 'asset_type');
    }
}
