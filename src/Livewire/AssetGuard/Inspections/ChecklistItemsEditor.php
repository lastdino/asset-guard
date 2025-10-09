<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Inspections;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View as IlluminateView;
use Lastdino\AssetGuard\Models\{AssetGuardInspectionChecklist as Checklist, AssetGuardInspectionChecklistItem as Item};
use Livewire\Component;
use Livewire\WithPagination;

class ChecklistItemsEditor extends Component
{
    use WithPagination;

    public int $checklistId;

    public bool $open = false;

    public ?int $editingId = null;

    /**
     * @var array{name:string,method:string,pass_condition:?array,min_value:float|null,max_value:float|null,sort_order:int}
     */
    public array $form = [
        'name' => '',
        'method' => 'text', // text|number|select|boolean
        'pass_condition' => null,
        'min_value' => null,
        'max_value' => null,
        'sort_order' => 0,
    ];

    public function mount(int $checklistId, bool $autoOpen = false): void
    {
        $this->checklistId = $checklistId;
        $this->open = $autoOpen;
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'method' => 'text',
            'pass_condition' => null,
            'min_value' => null,
            'max_value' => null,
            'sort_order' => 0,
        ];
        $this->open = true;
    }

    public function openEdit(int $id): void
    {
        $item = Item::query()->where('checklist_id', $this->checklistId)->findOrFail($id);
        $this->editingId = $item->id;
        $this->form = [
            'name' => (string) $item->name,
            'method' => (string) $item->method,
            'pass_condition' => $item->pass_condition,
            'min_value' => $item->min_value !== null ? (float) $item->min_value : null,
            'max_value' => $item->max_value !== null ? (float) $item->max_value : null,
            'sort_order' => (int) ($item->sort_order ?? 0),
        ];
        $this->open = true;
    }

    public function save(): void
    {
        $rules = [
            'form.name' => ['required','string','max:255'],
            'form.method' => ['required', Rule::in(['text','number','select','boolean'])],
            'form.pass_condition' => ['nullable','array'],
            'form.min_value' => ['nullable','numeric'],
            'form.max_value' => ['nullable','numeric'],
            'form.sort_order' => ['required','integer','min:0','max:65535'],
        ];

        $validated = $this->validate($rules, [], [
            'form.name' => __('asset-guard::checklists.item_name'),
            'form.method' => __('asset-guard::checklists.item_method'),
            'form.sort_order' => __('asset-guard::checklists.sort_order'),
        ]);

        $payload = $validated['form'];
        $payload['checklist_id'] = $this->checklistId;

        // Only keep range when method is number
        if ($payload['method'] !== 'number') {
            $payload['min_value'] = null;
            $payload['max_value'] = null;
        }

        if ($this->editingId) {
            Item::query()->where('checklist_id', $this->checklistId)->findOrFail($this->editingId)->update($payload);
        } else {
            Item::query()->create($payload);
        }

        $this->dispatch('checklist-items-updated', id: $this->checklistId);
        $this->open = false;
    }

    public function delete(int $id): void
    {
        Item::query()->where('checklist_id', $this->checklistId)->findOrFail($id)->delete();
        $this->dispatch('checklist-items-updated', id: $this->checklistId);
    }

    public function getItemsProperty(): LengthAwarePaginator
    {
        return Item::query()
            ->where('checklist_id', $this->checklistId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(10, pageName: 'itemsPage-'.$this->checklistId);
    }

    public function render(): IlluminateView
    {
        // ensure checklist exists to avoid orphan editing
        Checklist::query()->findOrFail($this->checklistId);

        return view('asset-guard::livewire.asset-guard.inspections.checklist-items-editor');
    }
}
