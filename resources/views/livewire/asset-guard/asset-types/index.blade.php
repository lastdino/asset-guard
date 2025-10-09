<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route(config('asset-guard.routes.prefix').'.dashboard.index') }}" icon="home" />
        <flux:breadcrumbs.item>{{ __('asset-guard::asset_types.title') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('asset-guard::asset_types.title') }}</h1>
        <flux:button variant="primary" wire:click="openCreate">{{ __('asset-guard::common.create') }}</flux:button>
    </div>

    <div class="flex items-center gap-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            :placeholder="__('asset-guard::common.search')"
            class="w-72"
        />
    </div>

    <div class="divide-y">
        @forelse ($this->types as $type)
            <div class="flex items-center justify-between py-3" wire:key="type-{{ $type->id }}">
                <div class="min-w-0">
                    <div class="font-medium text-black/90">{{ $type->name }} <span class="text-sm text-black/60">{{ $type->code }}</span></div>
                    <div class="text-sm text-black/60">
                        {{ __('asset-guard::asset_types.sort_order') }}: {{ $type->sort_order }} • assets: {{ $type->assets_count }}
                    </div>
                    @if($type->description)
                        <div class="text-sm text-black/70 mt-1">{{ $type->description }}</div>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <livewire:asset-guard.asset-types.checklist-manager :asset-type-id="$type->id" :key="$type->id"/>
                    <flux:button variant="subtle" wire:click="openEdit({{ $type->id }})">{{ __('asset-guard::common.edit') }}</flux:button>
                    <flux:button variant="danger" wire:click="delete({{ $type->id }})">{{ __('asset-guard::common.delete') }}</flux:button>
                </div>
            </div>
        @empty
            <div class="py-8 text-center text-black/60">{{ __('asset-guard::asset_types.none') }}</div>
        @endforelse
    </div>

    <div>
        {{ $this->types->links() }}
    </div>

    <flux:modal wire:model="openModal">
        <div class="space-y-4">
            <flux:input wire:model.defer="form.name" label="{{__('asset-guard::asset_types.name')}}"/>
            <flux:input wire:model.defer="form.code" label="{{__('asset-guard::asset_types.code')}}"/>
            <flux:textarea wire:model.defer="form.description" label="{{__('asset-guard::asset_types.description')}}" rows="3"/>
            <flux:input type="number" wire:model.defer="form.sort_order" label="{{__('asset-guard::asset_types.sort_order')}}"/>

            <div class="flex items-center gap-3">
                <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('asset-guard::common.save') }}</span>
                    <span wire:loading>{{ __('asset-guard::common.saving') }}</span>
                </flux:button>
                <flux:button variant="ghost" wire:click="$set('openModal', false)">{{ __('asset-guard::common.cancel') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
