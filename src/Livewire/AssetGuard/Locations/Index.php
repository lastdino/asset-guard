<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Locations;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View as IlluminateView;
use Illuminate\Validation\Rule;
use Lastdino\AssetGuard\Models\AssetGuardLocation as Location;
use Livewire\WithPagination;
use Livewire\Component;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $openModal = false;

    public ?int $editingId = null;

    /**
     * @var array{name:string,parent_id:int|null,meta:array}
     */
    public array $form = [
        'name' => '',
        'parent_id' => null,
        'meta' => [],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->form = ['name' => '', 'parent_id' => null, 'meta' => []];
        $this->openModal = true;
    }

    public function openEdit(int $id): void
    {
        $loc = Location::query()->findOrFail($id);
        $this->editingId = $loc->id;
        $this->form = [
            'name' => $loc->name,
            'parent_id' => $loc->parent_id,
            'meta' => $loc->meta ?? [],
        ];
        $this->openModal = true;
    }

    public function save(): void
    {
        $id = $this->editingId;

        $rules = [
            'form.name' => [
                'required', 'string', 'max:255',
                Rule::unique('asset_guard_locations', 'name')->ignore($id),
            ],
            'form.parent_id' => ['nullable', 'integer', 'exists:asset_guard_locations,id'],
            'form.meta' => ['nullable', 'array'],
        ];

        $validated = $this->validate($rules, [], [
            'form.name' => __('app.asset_guard.locations.name'),
            'form.parent_id' => __('app.asset_guard.locations.parent'),
        ]);

        if ($id) {
            $loc = Location::query()->findOrFail($id);
            $loc->update($validated['form']);
        } else {
            $loc = Location::query()->create($validated['form']);
        }

        $this->dispatch('saved');
        $this->openModal = false;
    }

    public function delete(int $id): void
    {
        $loc = Location::query()->withCount('assets')->findOrFail($id);
        if ($loc->assets_count > 0) {
            $this->addError('delete', __('asset-guard::validation.custom.cannot_delete_in_use'));
            return;
        }

        $loc->delete();
        $this->dispatch('deleted');
    }

    public function getLocationsProperty(): LengthAwarePaginator
    {
        return Location::query()
            ->with(['parent:id,name'])
            ->withCount(['children', 'assets'])
            ->when($this->search !== '', fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);
    }

    public function render(): IlluminateView
    {
        return view('asset-guard::livewire.asset-guard.locations.index', [
            'parents' => Location::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
