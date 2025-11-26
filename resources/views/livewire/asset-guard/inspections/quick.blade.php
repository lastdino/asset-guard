
<div class="p-6 space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route(config('asset-guard.routes.prefix').'.dashboard.index') }}" icon="home" />
        <flux:breadcrumbs.item>{{ __('asset-guard::quick_inspection.title') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('asset-guard::quick_inspection.title') }}</h1>
        @if ($inspectorSetupConfirmed)
            <div class="flex items-center gap-2">
                <flux:badge>
                    {{ __('asset-guard::inspections.inspector') }}: {{ optional(\App\Models\User::find($inspectorId))->name }}
                </flux:badge>
                <flux:button variant="subtle" wire:click="resetInspector">{{ __('asset-guard::inspections.reset_inspector') }}</flux:button>
            </div>
        @endif
    </div>

    @if ($inspectorSetupConfirmed)
        <div class="bg-white dark:bg-neutral-900 rounded p-4 space-y-4">
            <flux:field :label="__('asset-guard::quick_inspection.asset_code')">
                <div class="flex gap-2">
                    <flux:input wire:model.live.debounce.300ms="code" :placeholder="__('asset-guard::quick_inspection.asset_code_ph')" />
                    <flux:button variant="filled" wire:click="searchAndOpen">{{ __('asset-guard::quick_inspection.search_and_open') }}</flux:button>
                </div>
            </flux:field>

            @if ($message)
                <div class="text-sm text-red-600">{{ $message }}</div>
            @endif

            @if ($foundAssetId)
                <div class="text-sm text-neutral-600 dark:text-neutral-300">
                    {{ __('asset-guard::quick_inspection.asset_found') }}: #{{ $foundAssetId }}
                </div>
            @endif
        </div>
    @else
        <div class="space-y-4">
            <div class="rounded border p-4 text-sm text-neutral-600 dark:border-neutral-700 dark:text-neutral-300">
                {{ __('asset-guard::inspections.please_set_inspector_hint') }}
            </div>

            <div class="bg-white dark:bg-neutral-900 rounded p-4 space-y-3">
                <flux:select :label="__('asset-guard::inspections.inspector')" wire:model.live="inspectorId">
                    <flux:select.option value=""></flux:select.option>
                    @foreach(\App\Models\User::query()->orderBy('name')->get(['id','name']) as $u)
                        <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select :label="__('asset-guard::inspections.co_inspectors')" multiple wire:model.live="coInspectorIds">
                    <flux:select.option value=""></flux:select.option>
                    @foreach(\App\Models\User::query()->orderBy('name')->get(['id','name']) as $u)
                        <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="flex justify-end gap-2">
                    <flux:button variant="primary" wire:click="confirmInspectorSetup">{{ __('asset-guard::common.confirm') }}</flux:button>
                </div>
            </div>
        </div>
    @endif

    @livewire(\Lastdino\AssetGuard\Livewire\AssetGuard\Inspections\PerformerUnified::class)

    <flux:modal wire:model="showStartModal">
        <flux:heading size="md">{{ __('asset-guard::inspections.start_inspection') }}</flux:heading>
        <div class="space-y-6">
            <div class="bg-white dark:bg-neutral-900 rounded p-4 space-y-3">
                @if ($pendingOccurrenceId)
                    <div class="rounded border p-3 text-sm dark:border-neutral-700">
                        <div class="font-medium mb-1">{{ __('asset-guard::inspections.scheduled_exists') }}</div>
                        <div class="text-neutral-600 dark:text-neutral-300">{{ __('asset-guard::inspections.scheduled_hint') }}</div>
                    </div>
                @endif

                @if (!empty($availableChecklists))
                    <flux:field :label="__('asset-guard::inspections.select_checklist')">
                        <flux:select wire:model.live="selectedChecklistId">
                            @foreach ($availableChecklists as $cl)
                                <option value="{{ $cl['id'] }}">{{ $cl['name'] }} @if($cl['pre_use']) ({{ __('asset-guard::inspections.pre_use') }}) @endif</option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                @else
                    <div class="text-sm text-neutral-500">{{ __('asset-guard::quick_inspection.no_plan') }}</div>
                @endif
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="subtle" wire:click="$set('showStartModal', false)">{{ __('asset-guard::common.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="startSelectedInspection">{{ __('asset-guard::inspections.start') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
