<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Inspections;

use Lastdino\AssetGuard\Models\AssetGuardAsset;
use Lastdino\AssetGuard\Models\AssetGuardInspectionChecklistItem;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ChecklistItemsPanel extends Component
{
    use WithFileUploads;

    public int $assetId;

    public ?int $checklistId = null;

    public array $itemForm = [
        'id' => null,
        'name' => '',
        'method' => 'text',
        'pass_condition' => null,
        'min_value' => null,
        'max_value' => null,
        'checklist_id' => null,
    ];

    // 単一の参照写真（必要なら配列に変更）
    public $referencePhoto = null;

    public bool $showItemModal = false;

    public function mount(int $assetId, ?int $checklistId = null): void
    {
        $this->assetId = $assetId;
        $this->checklistId = $checklistId;
    }

    protected function rules(): array
    {
        return [
            'itemForm.id' => ['nullable', 'integer'],
            'itemForm.name' => ['required', 'string', 'max:255'],
            'itemForm.method' => ['required', 'in:text,number,select,boolean'],
            'itemForm.pass_condition' => ['nullable', 'array'],
            'itemForm.min_value' => ['nullable', 'numeric'],
            'itemForm.max_value' => ['nullable', 'numeric'],
            'itemForm.checklist_id' => ['nullable', 'integer'],
            'referencePhoto' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function newItem(?int $checklistId = null): void
    {
        $this->itemForm = [
            'id' => null,
            'name' => '',
            'method' => 'text',
            'pass_condition' => null,
            'min_value' => null,
            'max_value' => null,
            'checklist_id' => $checklistId ?? $this->checklistId,
        ];
        $this->referencePhoto = null;
        $this->showItemModal = true;
    }

    public function openEdit(int $id): void
    {
        if (! $this->checklistId) {
            return;
        }
        $item = AssetGuardInspectionChecklistItem::query()
            ->where('checklist_id', $this->checklistId)
            ->findOrFail($id);

        $this->itemForm = [
            'id' => (int) $item->id,
            'name' => $item->name,
            'method' => $item->method,
            'pass_condition' => $item->pass_condition,
            'min_value' => $item->min_value,
            'max_value' => $item->max_value,
            'checklist_id' => (int) $item->checklist_id,
        ];
        $this->referencePhoto = null;
        $this->showItemModal = true;
    }

    public function createOrUpdateItem(): void
    {
        $validated = $this->validate();
        $itemData = $validated['itemForm'];

        // min/max only apply to number method
        if ($itemData['method'] !== 'number') {
            $itemData['min_value'] = null;
            $itemData['max_value'] = null;
        } else {
            $min = $itemData['min_value'] ?? null;
            $max = $itemData['max_value'] ?? null;
            if ($min !== null && $max !== null && (float) $min > (float) $max) {
                $this->addError('itemForm.min_value', '下限は上限以下にしてください。');

                return;
            }
        }

        $targetChecklistId = $itemData['checklist_id'] ?? $this->checklistId;

        if (! $targetChecklistId) {
            $this->dispatch('notify', body: '点検票を選択してください');

            return;
        }

        $payload = $itemData;
        $payload['checklist_id'] = (int) $targetChecklistId;

        if (! empty($payload['id'])) {
            $item = AssetGuardInspectionChecklistItem::query()
                ->where('checklist_id', $targetChecklistId)
                ->findOrFail((int) $payload['id']);
            $item->update($payload);
        } else {
            $item = AssetGuardInspectionChecklistItem::query()->create($payload);
        }

        // 参照写真の保存（任意）
        if ($this->referencePhoto) {
            $item->addMedia($this->referencePhoto->getRealPath())
                ->usingFileName($this->referencePhoto->getClientOriginalName())
                ->toMediaCollection('reference_photos');
            $this->referencePhoto = null;
        }

        $this->dispatch('item-saved');
        $this->showItemModal = false;
    }

    public function deleteItem(int $id): void
    {
        if (! $this->checklistId) {
            return;
        }
        AssetGuardInspectionChecklistItem::query()
            ->where('checklist_id', $this->checklistId)
            ->whereKey($id)
            ->delete();

        $this->dispatch('item-deleted');
    }

    public function deleteReferencePhoto(int $mediaId): void
    {
        if (! $this->itemForm['id']) {
            return;
        }
        $item = AssetGuardInspectionChecklistItem::query()
            ->where('checklist_id', $this->checklistId)
            ->findOrFail((int) $this->itemForm['id']);

        $item->media()->whereKey($mediaId)->first()?->delete();
        $this->dispatch('notify', body: '参照写真を削除しました');
    }

    public function getChecklistsProperty()
    {
        return AssetGuardAsset::query()
            ->with('inspectionChecklists.items')
            ->find($this->assetId)?->inspectionChecklists ?? collect();
    }

    #[On('checklist-saved')]
    #[On('checklist-deleted')]
    public function refreshChecklists(): void
    {
        if ($this->checklistId && ! $this->getChecklistsProperty()->firstWhere('id', $this->checklistId)) {
            $this->checklistId = null;
        }

        $this->dispatch('$refresh');
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.inspections.checklist-items-panel');
    }
}
