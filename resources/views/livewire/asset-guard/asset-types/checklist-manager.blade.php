<div class="flex items-center gap-2">

    <flux:button variant="subtle" wire:click="$set('openManage', true)">
        {{ __('asset-guard::checklists.manage') }}
    </flux:button>

    <!-- Manage Flyout Modal -->
    <flux:modal wire:model="openManage" variant="flyout">
        <div class="space-y-4 mt-3">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium">{{ __('asset-guard::checklists.manage') }}</h2>
                <flux:button variant="primary" size="sm" wire:click="openCreate">{{ __('asset-guard::common.create') }}</flux:button>
            </div>

            <div class="flex items-center gap-3">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    :placeholder="__('asset-guard::common.search')"
                    class="w-72"
                />
            </div>

            <div x-data="{ openIds: {} }" class="divide-y border rounded-md">
                @forelse ($this->checklists as $cl)
                    <div class="p-0" wire:key="group-{{ $cl->id }}">
                        <div class="flex items-center justify-between p-3">
                            <div class="min-w-0">
                                <div class="font-medium text-black/90 flex items-center gap-2">
                                    <span class="truncate">{{ $cl->name }}</span>
                                    <flux:badge>
                                        {{ $cl->active ? __('asset-guard::common.active') : __('asset-guard::common.inactive') }}
                                    </flux:badge>
                                </div>
                                <div class="text-sm text-black/60 mt-0.5">
                                    {{ __('asset-guard::checklists.frequency') }}:
                                    @if($cl->frequency_unit && $cl->frequency_value)
                                        {{ $cl->frequency_value }} {{ __('asset-guard::checklists.'.$cl->frequency_unit) }}
                                    @else
                                        {{ __('asset-guard::checklists.not_set') }}
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:button variant="subtle" x-on:click="openIds[{{ $cl->id }}] = !openIds[{{ $cl->id }}]">
                                    <span x-show="!openIds[{{ $cl->id }}]">{{ __('asset-guard::common.show') }}</span>
                                    <span x-show="openIds[{{ $cl->id }}]">{{ __('asset-guard::common.hide') }}</span>
                                </flux:button>

                                <flux:button variant="subtle" wire:click="openEdit({{ $cl->id }})">{{ __('asset-guard::common.edit') }}</flux:button>
                                <flux:button variant="danger" wire:click="delete({{ $cl->id }})">{{ __('asset-guard::common.delete') }}</flux:button>
                            </div>
                        </div>
                        <flux:separator text="{{__('asset-guard::checklist_items.heading')}}" />

                        <div class="p-3" x-show="openIds[{{ $cl->id }}]" x-transition x-cloak>
                            <livewire:asset-guard.inspections.checklist-items-editor :checklist-id="$cl->id" :key="'items-'.$cl->id" />
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-black/60">{{ __('asset-guard::checklists.none') }}</div>
                @endforelse
            </div>

            <div>
                {{ $this->checklists->links() }}
            </div>
        </div>
    </flux:modal>

    <!-- Create/Edit Form Modal -->
    <flux:modal wire:model="openModal">
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium">
                    @if($editingId)
                        {{ __('asset-guard::common.edit') }}
                    @else
                        {{ __('asset-guard::common.create') }}
                    @endif
                </h2>
            </div>

            <div class="space-y-3">
                <flux:input wire:model.defer="form.name" label="{{ __('asset-guard::checklists.name') }}" />

                <div class="grid grid-cols-2 gap-3">
                    <flux:select wire:model.defer="form.frequency_unit" label="{{ __('asset-guard::checklists.frequency_unit') }}">
                        <option value="">--</option>
                        <option value="day">{{ __('asset-guard::checklists.day') }}</option>
                        <option value="week">{{ __('asset-guard::checklists.week') }}</option>
                        <option value="month">{{ __('asset-guard::checklists.month') }}</option>
                        <option value="year">{{ __('asset-guard::checklists.year') }}</option>
                    </flux:select>
                    <flux:input type="number" wire:model.defer="form.frequency_value" label="{{ __('asset-guard::checklists.frequency_value') }}" min="1" />
                </div>

                <div class="flex items-center gap-2">
                    <flux:checkbox wire:model.defer="form.active"/>
                    <span class="text-sm">{{ __('asset-guard::checklists.active') }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <flux:checkbox wire:model.defer="form.require_before_activation"/>
                    <span class="text-sm">{{ __('asset-guard::checklists.require_before_activation') }}</span>
                </div>

                <div class="flex items-center gap-3">
                    <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove>{{ __('asset-guard::common.save') }}</span>
                        <span wire:loading>{{ __('asset-guard::common.saving') }}</span>
                    </flux:button>
                    <flux:button variant="ghost" wire:click="$set('openModal', false)">{{ __('asset-guard::common.close') }}</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
