<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetGuardInspectionSchedule extends Model
{
    protected $fillable = [
        'asset_id', 'checklist_item_id', 'due_date', 'last_done_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'last_done_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AssetGuardAsset::class, 'asset_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AssetGuardInspectionChecklistItem::class, 'checklist_item_id');
    }
}
