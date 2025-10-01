<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route(config('asset-guard.routes.prefix').'.dashboard.index') }}" icon="home" />
        <flux:breadcrumbs.item>{{ __('asset-guard::locations.title') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('asset-guard::locations.title') }}</h1>
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
        @forelse ($this->locations as $loc)
            <div class="flex items-center justify-between py-3" wire:key="loc-{{ $loc->id }}">
                <div class="min-w-0">
                    <div class="font-medium text-black/90">{{ $loc->name }}</div>
                    <div class="text-sm text-black/60">
                        {{ __('asset-guard::locations.parent') }}:
                        {{ optional($loc->parent)->name ?? '—' }}
                        • children: {{ $loc->children_count }} • assets: {{ $loc->assets_count }}
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <flux:button variant="subtle" wire:click="openEdit({{ $loc->id }})">{{ __('asset-guard::common.edit') }}</flux:button>
                    <flux:button variant="danger" wire:click="delete({{ $loc->id }})">{{ __('asset-guard::common.delete') }}</flux:button>
                </div>
            </div>
        @empty
            <div class="py-8 text-center text-black/60">{{ __('app.asset_guard.locations.none') }}</div>
        @endforelse
    </div>

    <div>
        {{ $this->locations->links() }}
    </div>

    <flux:modal wire:model="openModal">
        <div class="space-y-4">
            <flux:input wire:model.defer="form.name" label="{{__('asset-guard::locations.name')}}"/>
            <flux:select wire:model.defer="form.parent_id" label="{{__('asset-guard::locations.parent')}}">
                <option value="">—</option>
                @foreach($parents as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </flux:select>


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
