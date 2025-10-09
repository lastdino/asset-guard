<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\AssetTypes;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View as IlluminateView;
use Illuminate\Validation\Rule;
use Lastdino\AssetGuard\Models\AssetGuardAssetType as AssetType;
use Livewire\WithPagination;
use Livewire\Component;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $openModal = false;

    public ?int $editingId = null;

    /**
     * @var array{name:string,code:string|null,description:string|null,sort_order:int,meta:array}
     */
    public array $form = [
        'name' => '',
        'code' => null,
        'description' => null,
        'sort_order' => 0,
        'meta' => [],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'code' => null,
            'description' => null,
            'sort_order' => 0,
            'meta' => [],
        ];
        $this->openModal = true;
    }

    public function openEdit(int $id): void
    {
        $type = AssetType::query()->findOrFail($id);
        $this->editingId = $type->id;
        $this->form = [
            'name' => $type->name,
            'code' => $type->code,
            'description' => $type->description,
            'sort_order' => (int) ($type->sort_order ?? 0),
            'meta' => $type->meta ?? [],
        ];
        $this->openModal = true;
    }

    public function save(): void
    {
        $id = $this->editingId;

        $rules = [
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['nullable', 'string', 'max:255'],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'form.meta' => ['nullable', 'array'],
        ];

        $validated = $this->validate($rules, [], [
            'form.name' => __('asset-guard::asset_types.name'),
            'form.code' => __('asset-guard::asset_types.code'),
            'form.description' => __('asset-guard::asset_types.description'),
            'form.sort_order' => __('asset-guard::asset_types.sort_order'),
        ]);

        if ($id) {
            $type = AssetType::query()->findOrFail($id);
            $type->update($validated['form']);
        } else {
            $type = AssetType::query()->create($validated['form']);
        }

        $this->dispatch('saved');
        $this->openModal = false;
    }

    public function delete(int $id): void
    {
        $type = AssetType::query()->withCount('assets')->findOrFail($id);
        if ($type->assets_count > 0) {
            $this->addError('delete', __('asset-guard::validation.custom.cannot_delete_in_use'));
            return;
        }

        $type->delete();
        $this->dispatch('deleted');
    }

    public function getTypesProperty(): LengthAwarePaginator
    {
        return AssetType::query()
            ->withCount('assets')
            ->when($this->search !== '', fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10);
    }

    public function render(): IlluminateView
    {
        return view('asset-guard::livewire.asset-guard.asset-types.index');
    }
}
