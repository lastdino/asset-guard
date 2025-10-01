<div class="grid gap-3">
    <div class="flex items-center justify-between">
        <flux:heading size="sm">{{ __('asset-guard::checklist_items.heading') }}</flux:heading>
        <div class="flex items-center gap-2">
            <flux:select class="min-w-60" wire:model.live="checklistId">
                <option value="">{{ __('asset-guard::checklist_items.select_checklist') }}</option>
                @foreach($this->checklists as $c)
                    <option value="{{ $c->id }}">{{ $c->name }} @if(!$c->active)（{{ __('asset-guard::checklists.inactive') }}）@endif</option>
                @endforeach
            </flux:select>
            <flux:button variant="primary" size="xs" wire:click="newItem">{{ __('asset-guard::common.new') }}</flux:button>
        </div>
    </div>

    <div class="grid gap-2">
        @php($current = ($this->checklists->firstWhere('id', $checklistId) ?? null))
        @forelse(($current?->items ?? collect()) as $item)
            <div class="flex items-center justify-between rounded border p-2 dark:border-zinc-700">
                <div class="text-sm">
                    <div class="font-medium">{{ $item->name }}</div>
                    <div class="text-zinc-500">{{ $item->method }}
                        @if($item->method === 'number' && (!is_null($item->min_value) || !is_null($item->max_value)))
                            <span class="ml-2">({{ __('asset-guard::checklist_items.min') }}: {{ $item->min_value ?? '—' }} / {{ __('asset-guard::checklist_items.max') }}: {{ $item->max_value ?? '—' }})</span>
                        @endif
                    </div>
                </div>
                <div class="flex gap-2">
                    <flux:button size="xs" variant="subtle" wire:click="openEdit({{ $item->id }})">{{ __('asset-guard::common.edit') }}</flux:button>
                    <flux:button size="xs" variant="danger" wire:click="deleteItem({{ $item->id }})">{{ __('asset-guard::common.delete') }}</flux:button>
                </div>
            </div>
        @empty
            <div class="text-zinc-500">{{ __('asset-guard::checklist_items.empty') }}</div>
        @endforelse
    </div>

    <flux:modal wire:model="showItemModal">
        <flux:heading size="md">{{ __('asset-guard::checklist_items.modal_title') }}</flux:heading>
        <div class="mt-3 grid gap-3">
            <flux:input :label="__('asset-guard::checklist_items.name')" wire:model.defer="itemForm.name" />
            <flux:select :label="__('asset-guard::checklist_items.method')" wire:model.live="itemForm.method">
                <option value="text">{{ __('asset-guard::checklist_items.method_text') }}</option>
                <option value="number">{{ __('asset-guard::checklist_items.method_number') }}</option>
                <option value="select">{{ __('asset-guard::checklist_items.method_select') }}</option>
                <option value="boolean">{{ __('asset-guard::checklist_items.method_boolean') }}</option>
            </flux:select>

            @if(data_get($itemForm, 'method') === 'number')
                <div class="grid grid-cols-2 gap-2">
                    <flux:input type="number" step="0.0001" :label="__('asset-guard::checklist_items.min_optional')" placeholder="0" wire:model.defer="itemForm.min_value" />
                    <flux:input type="number" step="0.0001" :label="__('asset-guard::checklist_items.max_optional')" placeholder="100" wire:model.defer="itemForm.max_value" />
                </div>
                @error('itemForm.min_value')
                    <div class="text-xs text-red-600">{{ $message }}</div>
                @enderror
            @endif

            <div class="mt-2 grid gap-2">
                <flux:input type="file" :label="__('asset-guard::checklist_items.reference_photo')" wire:model="referencePhoto" accept="image/*" />
                @error('referencePhoto')
                    <div class="text-xs text-red-600">{{ $message }}</div>
                @enderror

                @php($refs = !empty($itemForm['id']) ? \Lastdino\AssetGuard\Models\AssetGuardInspectionChecklistItem::find($itemForm['id'])?->getMedia('reference_photos') : collect())
                @if($refs && $refs->isNotEmpty())
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($refs as $media)
                            @php($url = URL::temporarySignedRoute(config('asset-guard.routes.prefix').'.inspections.items.media', now()->addMinutes(10), ['media' => $media->id]))
                            <div class="flex items-center gap-2">
                                <img src="{{ $url }}" alt="参照写真" class="h-20 w-20 rounded object-cover border" />
                                <flux:button size="xs" variant="danger" wire:click="deleteReferencePhoto({{ $media->id }})">{{ __('asset-guard::common.delete') }}</flux:button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showItemModal', false)">{{ __('asset-guard::common.cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="createOrUpdateItem">{{ __('asset-guard::common.save') }}</flux:button>
        </div>
    </flux:modal>
</div>
