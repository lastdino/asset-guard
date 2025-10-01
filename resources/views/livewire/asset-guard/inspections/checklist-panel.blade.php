<div class="grid gap-3">
    <div class="flex items-center justify-between">
        <flux:heading size="sm">{{ __('asset-guard::checklists.heading') }}</flux:heading>
        <flux:button variant="primary" size="xs" wire:click="openCreate">{{ __('asset-guard::checklists.new') }}</flux:button>
    </div>

    <div class="grid gap-2">
        @foreach($this->checklists as $cl)
            <div class="flex items-center justify-between rounded border p-2 text-sm dark:border-zinc-700">
                <div>
                    <div class="font-medium">{{ $cl->name }}</div>
                    <div class="text-zinc-500 flex items-center gap-2">
                        <span>
                            @if(!$cl->active)（{{ __('asset-guard::checklists.inactive') }}）@endif
                            {{ __('asset-guard::checklists.items_count', ['count' => $cl->items->count()]) }}
                        </span>
                        @if(($cl->frequency_unit ?? null) === 'PerUse')
                            <flux:badge>{{ __('asset-guard::checklists.frequency_unit_options.PerUse') }}</flux:badge>
                        @else
                            <span>・{{ __('asset-guard::checklists.frequency_short', ['value' => (int)($cl->frequency_value ?? 1), 'unit' => __('asset-guard::checklists.frequency_unit_options.'.($cl->frequency_unit ?? 'Monthly'))]) }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex gap-2">
                    <flux:button size="xs" variant="subtle" wire:click="openEdit({{ $cl->id }})">{{ __('asset-guard::common.edit') }}</flux:button>
                    <flux:button size="xs" variant="danger" wire:click="delete({{ $cl->id }})">{{ __('asset-guard::common.delete') }}</flux:button>
                </div>
            </div>
        @endforeach
        @if($this->checklists->isEmpty())
            <div class="text-zinc-500">{{ __('asset-guard::checklists.empty') }}</div>
        @endif
    </div>

    <flux:modal wire:model="showModal">
        <flux:heading size="md">{{ __('asset-guard::checklists.modal_title') }}</flux:heading>
        <div class="mt-3 grid gap-3" x-data="{ unit: @entangle('form.frequency_unit') }">
            <flux:input :label="__('asset-guard::checklists.name')" wire:model.defer="form.name"/>
            <flux:select :label="__('asset-guard::checklists.applies_to')" wire:model.defer="form.applies_to">
                <option value="asset">{{ __('asset-guard::checklists.applies_to_asset') }}</option>
                <option value="type">{{ __('asset-guard::checklists.applies_to_type') }}</option>
            </flux:select>

            <div class="grid grid-cols-2 gap-2">
                <flux:input type="number" min="1" :label="__('asset-guard::checklists.frequency_value')" wire:model.defer="form.frequency_value" x-bind:disabled="unit === 'PerUse'" />
                <flux:select :label="__('asset-guard::checklists.frequency_unit')" wire:model.defer="form.frequency_unit">
                    @foreach (['OneTime','PerUse','Daily','Weekly','Monthly','Quarterly','SemiAnnual','Annual','Custom'] as $u)
                        <option value="{{ $u }}">{{ __('asset-guard::checklists.frequency_unit_options.'.$u) }}</option>
                    @endforeach
                </flux:select>
            </div>

            <flux:switch :label="__('asset-guard::checklists.active')" wire:model.defer="form.active" />
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showModal', false)">{{ __('asset-guard::common.cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="save">{{ __('asset-guard::common.save') }}</flux:button>
        </div>
    </flux:modal>
</div>
