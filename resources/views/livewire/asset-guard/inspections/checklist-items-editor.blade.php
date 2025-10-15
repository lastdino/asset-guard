<div>
    @unless($readonly)
        <flux:button variant="subtle" size="xs" wire:click="openCreate">
            {{ __('asset-guard::checklists.add_item') }}
        </flux:button>
    @endunless

    <div class="mt-2 grid gap-2">
        @foreach($this->items as $it)
            <div class="flex items-center justify-between rounded border p-2 text-sm dark:border-zinc-700" wire:key="item-{{ $it->id }}">
                <div class="min-w-0">
                    <div class="font-medium truncate">{{ $it->name }}</div>
                    <div class="text-black/60 text-xs">
                        {{ __('asset-guard::checklists.item_method') }}: {{ $it->method }}
                        @if($it->min_value !== null || $it->max_value !== null)
                            • {{ __('asset-guard::checklists.range') }}: {{ $it->min_value ?? '-' }} ~ {{ $it->max_value ?? '-' }}
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @unless($readonly)
                        <flux:button variant="subtle" size="xs" wire:click="openEdit({{ $it->id }})">{{ __('asset-guard::common.edit') }}</flux:button>
                        <flux:button variant="danger" size="xs" wire:click="delete({{ $it->id }})">{{ __('asset-guard::common.delete') }}</flux:button>
                    @endunless
                </div>
            </div>
        @endforeach

        {{ $this->items->links() }}
    </div>

    <flux:modal wire:model="open">
        <div class="mt-3 grid gap-3">
            <flux:input :label="__('asset-guard::checklist_items.name')" wire:model.defer="form.name" />
            <flux:select :label="__('asset-guard::checklist_items.method')" wire:model.live="form.method">
                <option value="text">{{ __('asset-guard::checklist_items.method_text') }}</option>
                <option value="number">{{ __('asset-guard::checklist_items.method_number') }}</option>
                <option value="select">{{ __('asset-guard::checklist_items.method_select') }}</option>
                <option value="boolean">{{ __('asset-guard::checklist_items.method_boolean') }}</option>
            </flux:select>

            @if(data_get($form, 'method') === 'number')
                <div class="grid grid-cols-2 gap-2">
                    <flux:input type="number" step="0.0001" :label="__('asset-guard::checklist_items.min_optional')" placeholder="0" wire:model.defer="form.min_value" />
                    <flux:input type="number" step="0.0001" :label="__('asset-guard::checklist_items.max_optional')" placeholder="100" wire:model.defer="form.max_value" />
                </div>
                @error('itemForm.min_value')
                <div class="text-xs text-red-600">{{ $message }}</div>
                @enderror
            @endif

            @unless($readonly)
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
            @endunless
            <div class="grid grid-cols-3 gap-2">
                <flux:input type="number" :label="__('asset-guard::checklists.sort_order')" wire:model.defer="form.sort_order" />
            </div>
        </div>
        <div class="flex justify-end gap-2 mt-2">
            <flux:button variant="ghost" wire:click="$set('open', false)">{{ __('asset-guard::common.cancel') }}</flux:button>
            @unless($readonly)
                <flux:button variant="primary" wire:click="save">{{ __('asset-guard::common.save') }}</flux:button>
            @endunless
        </div>
    </flux:modal>
</div>
