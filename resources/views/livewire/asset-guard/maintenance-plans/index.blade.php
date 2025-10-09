<div class="p-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route(config('asset-guard.routes.prefix').'.dashboard.index') }}" icon="home" />
        <flux:breadcrumbs.item>保全計画</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">{{ __('asset-guard::maintenance_plans.title') }}</h1>
        <div class="flex gap-2">
            <flux:button variant="ghost" href="{{ route(config('asset-guard.routes.prefix').'.maintenance-plans.index') }}">{{ __('asset-guard::maintenance_plans.refresh') }}</flux:button>
            <flux:button variant="filled" wire:click="openCreate">{{ __('asset-guard::maintenance_plans.new') }}</flux:button>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12 lg:col-span-4">
            <div class="bg-white dark:bg-neutral-900 rounded p-4 mb-4">
                <flux:field :label="__('asset-guard::maintenance_plans.asset_filter')">
                    <flux:select wire:model.live="assetId">
                        <option value="">{{ __('asset-guard::maintenance_plans.all') }}</option>
                        @foreach (\Lastdino\AssetGuard\Models\AssetGuardAsset::query()->orderBy('name')->get(['id','name']) as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            <div class="bg-white dark:bg-neutral-900 rounded p-4">
                <div class="text-sm text-neutral-600 dark:text-neutral-300 mb-3">
                    {{ __('asset-guard::maintenance_plans.total', ['count' => $this->plans->count()]) }}
                </div>
                <div class="divide-y divide-neutral-200/70 dark:divide-neutral-700/70">
                    @forelse ($this->plans as $plan)
                        <div class="py-3">
                            <div class="font-medium">
                                <a class="text-blue-600 hover:underline"
                                   href=""
                                   wire:click.prevent="openShow({{ $plan->id }})"
                                >{{ $plan->title }}</a>
                                <flux:button class="ml-2" size="xs" variant="subtle" wire:click="openEdit({{ $plan->id }})">
                                    {{ __('asset-guard::common.edit') }}
                                </flux:button>
                            </div>
                            <div class="text-sm text-neutral-600 dark:text-neutral-300">
                                {{ __('asset-guard::maintenance_plans.asset') }}: {{ optional($plan->asset)->name ?? '#'.$plan->asset_id }} / {{ __('asset-guard::maintenance_plans.start') }}: {{ $plan->start_date?->toDateString() }}
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-neutral-500">{{ __('asset-guard::maintenance_plans.no_plans') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-8">
            <div id="maintenance-calendar" x-data="initCalendar" wire:ignore class="bg-white dark:bg-neutral-900 rounded p-4"></div>
        </div>
    </div>

    {{-- Create modal --}}
    <flux:modal wire:model="showCreate">
        <flux:heading size="md">{{ __('asset-guard::maintenance_plans.new') }}</flux:heading>
        {{-- Inline form (create) --}}
        <div class="">
            <div class="space-y-4 bg-white dark:bg-neutral-900 rounded p-4">
                <flux:select wire:model.live="form.asset_id" label="{{__('asset-guard::maintenance_plans.asset')}}">
                    <option >設備を選択</option>
                    @foreach ($this->assets as $asset)
                        <option value="{{ $asset->id }}">{{ $asset->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="form.checklist_id" :disabled="empty($form['asset_id'])" label="{{__('asset-guard::maintenance_plans.checklist')}}">
                    @if (empty($form['asset_id']))
                        <option value="">{{ __('asset-guard::maintenance_plans.select_asset_first') }}</option>
                    @else
                        @foreach ($this->checklists as $cl)
                            <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                        @endforeach
                    @endif
                </flux:select>

                <flux:textarea wire:model.live="form.description" rows="4" label="{{__('asset-guard::maintenance_plans.description')}}"/>

                <flux:input type="number" min="0" max="30" wire:model.live="form.lead_time_days" label="{{__('asset-guard::maintenance_plans.lead_time_days')}}"/>


                <div class="grid grid-cols-2 gap-4">
                    <flux:input type="date" wire:model.live="form.start_date" label="{{__('asset-guard::maintenance_plans.start_date')}}"/>
                    <flux:input type="date" wire:model.live="form.end_date" label="{{__('asset-guard::maintenance_plans.end_date')}}"/>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model.live="form.timezone" label="{{__('asset-guard::maintenance_plans.timezone')}}"/>
                    <flux:select wire:model.live="form.assigned_to" label="{{__('asset-guard::maintenance_plans.assigned_to')}}">
                        <option value="">{{ __('asset-guard::maintenance_plans.unassigned') }}</option>
                        @foreach (\App\Models\User::query()->orderBy('name')->get(['id','name']) as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:select wire:model.live="form.status" label="{{__('asset-guard::maintenance_plans.status')}}">
                    @foreach (['Draft','Scheduled','Paused','Completed','Cancelled'] as $st)
                        <option value="{{ $st }}">{{ $st }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="space-y-4">
                <div class="bg-white dark:bg-neutral-900 rounded p-4">
                    <div class="text-sm text-neutral-600 dark:text-neutral-300">
                        {{ __('asset-guard::maintenance_plans.after_saving_hint') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="closeModals">{{ __('asset-guard::common.back') }}</flux:button>
            <flux:button variant="filled" wire:click="save" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('asset-guard::common.save') }}</span>
                <span wire:loading>{{ __('asset-guard::common.saving') }}</span>
            </flux:button>
        </div>
    </flux:modal>

    {{-- Show modal --}}
    <flux:modal wire:model="showShow" class="w-2xl">
        <flux:heading size="md">{{ __('asset-guard::maintenance_plans.title') }}</flux:heading>
        @if($viewingPlan)
            <div class="space-y-3">
                <div class="text-sm text-neutral-600 dark:text-neutral-300">
                    {{ __('asset-guard::maintenance_plans.asset') }}:
                    {{ optional($viewingPlan->asset)->name ?? ('#'.$viewingPlan->asset_id) }}
                </div>
                <div class="text-sm text-neutral-600 dark:text-neutral-300">
                    {{ __('asset-guard::maintenance_plans.start') }}: {{ $viewingPlan->start_date?->toDateString() }}
                </div>
                <div class="text-sm text-neutral-600 dark:text-neutral-300">
                    {{ __('asset-guard::maintenance_plans.status') }}: {{ $viewingPlan->status }}
                </div>

                <div class="mt-4">
                    <div class="font-medium mb-2">{{ __('asset-guard::inspections.upcoming') ?? 'Upcoming' }}</div>
                    <div class="grid gap-2">
                        @forelse($upcomingOccurrences as $o)
                            <div class="flex items-center justify-between text-sm text-neutral-700 dark:text-neutral-200">
                                <div>
                                    {{ isset($o['planned_at']) ? \Carbon\Carbon::parse($o['planned_at'])->format('Y-m-d H:i') : '' }} — {{ data_get($o, 'asset.name') ?? ('#'.data_get($o,'asset_id')) }}
                                </div>
                                <div class="shrink-0 flex items-center gap-2">
                                    <flux:button variant="subtle" size="xs" wire:click="openOccurrenceEdit({{ (int) $o['id'] }})">
                                        {{ __('asset-guard::common.edit') }}
                                    </flux:button>
                                    <flux:button variant="danger" size="xs" wire:click="confirmDeleteOccurrence({{ (int) $o['id'] }})">
                                        {{ __('asset-guard::common.delete') }}
                                    </flux:button>
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-neutral-500">{{ __('asset-guard::maintenance_plans.no_plans') }}</div>
                        @endforelse
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    @if($viewingPlanId)
                        <flux:button variant="subtle" wire:click="openEdit({{ $viewingPlanId }})">{{ __('asset-guard::common.edit') }}</flux:button>
                    @endif
                    <flux:button variant="ghost" wire:click="closeModals">{{ __('asset-guard::common.back') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Edit modal --}}
    <flux:modal wire:model="showEdit">
        <flux:heading size="md">{{ __('asset-guard::maintenance_plans.edit_title') }}</flux:heading>
        {{-- Inline form (edit) --}}
        <div class="">
            <div class="space-y-4 bg-white dark:bg-neutral-900 rounded p-4">
                <flux:select wire:model.live="form.asset_id" label="{{__('asset-guard::maintenance_plans.asset')}}">
                    @foreach ($this->assets as $asset)
                        <option value="{{ $asset->id }}">{{ $asset->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="form.checklist_id" :disabled="empty($form['asset_id'])" label="{{__('asset-guard::maintenance_plans.checklist')}}">
                    @if (empty($form['asset_id']))
                        <option value="">{{ __('asset-guard::maintenance_plans.select_asset_first') }}</option>
                    @else
                        @foreach ($this->checklists as $cl)
                            <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                        @endforeach
                    @endif
                </flux:select>
                <flux:textarea wire:model.live="form.description" rows="4" label="{{__('asset-guard::maintenance_plans.description')}}"/>

                <flux:input type="number" min="0" max="30" wire:model.live="form.lead_time_days" label="{{__('asset-guard::maintenance_plans.lead_time_days')}}"/>


                <div class="grid grid-cols-2 gap-4">
                    <flux:input type="date" wire:model.live="form.start_date" label="{{__('asset-guard::maintenance_plans.start_date')}}"/>
                    <flux:input type="date" wire:model.live="form.end_date" label="{{__('asset-guard::maintenance_plans.end_date')}}"/>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model.live="form.timezone" label="{{__('asset-guard::maintenance_plans.timezone')}}"/>
                    <flux:select wire:model.live="form.assigned_to" label="{{__('asset-guard::maintenance_plans.assigned_to')}}">
                        <option value="">{{ __('asset-guard::maintenance_plans.unassigned') }}</option>
                        @foreach (\App\Models\User::query()->orderBy('name')->get(['id','name']) as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:field :label="__('asset-guard::maintenance_plans.status')">
                    <flux:select wire:model.live="form.status">
                        @foreach (['Draft','Scheduled','Paused','Completed','Cancelled'] as $st)
                            <option value="{{ $st }}">{{ $st }}</option>
                        @endforeach
                    </flux:select>
                    @error('form.status')
                    <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                    @enderror
                </flux:field>
            </div>

            <div class="space-y-4">
                <div class="bg-white dark:bg-neutral-900 rounded p-4">
                    <div class="text-sm text-neutral-600 dark:text-neutral-300">
                        {{ __('asset-guard::maintenance_plans.after_saving_hint') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="closeModals">{{ __('asset-guard::common.back') }}</flux:button>
            <flux:button variant="filled" wire:click="save" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('asset-guard::common.save') }}</span>
                <span wire:loading>{{ __('asset-guard::common.saving') }}</span>
            </flux:button>
        </div>
    </flux:modal>

    {{-- Occurrence Edit modal --}}
    <flux:modal wire:model="showOccurrenceEdit">
        <flux:heading size="md">{{ __('asset-guard::occurrences.edit_title') }}</flux:heading>

        <div class="space-y-4 bg-white dark:bg-neutral-900 rounded p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input type="datetime-local" label="{{__('asset-guard::occurrences.planned_at')}}" wire:model.live="occurrenceForm.planned_at" />
                <flux:input type="datetime-local" label="{{__('asset-guard::occurrences.due_at')}}" wire:model.live="occurrenceForm.due_at" />
            </div>

            <flux:select wire:model.live="occurrenceForm.status" label="{{__('asset-guard::occurrences.status')}}">
                @foreach (['Scheduled','Paused','Completed','Cancelled'] as $st)
                    <option value="{{ $st }}">{{ $st }}</option>
                @endforeach
            </flux:select>

            <flux:input type="datetime-local" wire:model.live="occurrenceForm.completed_at" label="{{__('asset-guard::occurrences.completed_at')}}"/>

            <flux:textarea rows="4" wire:model.live="occurrenceForm.notes" label="{{__('asset-guard::occurrences.notes')}}"/>

            <flux:select wire:model.live="occurrenceForm.assigned_to" label="{{__('asset-guard::common.assignee')}}">
                <option value="">{{ __('asset-guard::maintenance_plans.unassigned') }}</option>
                @foreach (\App\Models\User::query()->orderBy('name')->get(['id','name']) as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </flux:select>

        </div>

        <div class="mt-4 flex justify-between gap-2">
            <div>
                <flux:button variant="danger" wire:click="confirmDeleteOccurrence({{ (int) ($editingOccurrenceId ?? 0) }})">
                    {{ __('asset-guard::common.delete') }}
                </flux:button>
            </div>
            <div class="flex gap-2">
                <flux:button variant="ghost" wire:click="$set('showOccurrenceEdit', false)">{{ __('asset-guard::common.back') }}</flux:button>
                <flux:button variant="filled" wire:click="saveOccurrence" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('asset-guard::common.save') }}</span>
                    <span wire:loading>{{ __('asset-guard::common.saving') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Occurrence Delete confirm modal --}}
    <flux:modal wire:model="showOccurrenceDelete">
        <flux:heading size="md">{{ __('asset-guard::occurrences.delete_title') }}</flux:heading>
        <div class="p-4 text-sm text-neutral-700 dark:text-neutral-200">
            {{ __('asset-guard::occurrences.delete_confirm') }}
        </div>
        <div class="mt-2 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showOccurrenceDelete', false)">{{ __('asset-guard::common.cancel') }}</flux:button>
            <flux:button variant="danger" wire:click="deleteOccurrence" wire:loading.attr="disabled">
                <span>{{ __('asset-guard::common.delete') }}</span>
            </flux:button>
        </div>
    </flux:modal>
</div>

@script
<script>
    Alpine.data('initCalendar', () => ({
        init() {
            const calendarEl = document.getElementById('maintenance-calendar');
            const calendar = new FullCalendar.Calendar(calendarEl, {
                plugins: [
                    FullCalendar.dayGridPlugin,
                    FullCalendar.interactionPlugin
                ],
                initialView: 'dayGridMonth',
                locale: FullCalendar.jaLocale,
                editable: true,
                eventSources: [
                    {
                        events:function(fetchInfo, successCallback, failureCallback) {
                            var data = $wire.loadCalendar(fetchInfo);
                            data.then(function (value) {
                                //カレンダー情報をカレンダーに送る
                                successCallback(value);
                            })
                        },
                    }

                ],

                eventClick: info => {
                    const Id = info.event.id;
                    if (Id) {
                        info.jsEvent.preventDefault();
                        Livewire.dispatch('open-occurrence-show', { occurrenceId: Id });
                        return false;
                    }
                    if (info.event.url) { window.location = info.event.url; }
                },
                //イベントを移動したとき
                eventDrop: function(info) {
                    $wire.reschedule(parseInt(info.event.id),info.event.start.toISOString());
                },
            });
            calendar.render();
            $wire.$on('refreshCalendar', () => {
                calendar.refetchEvents();
            })
        },

    }))
</script>
@endscript
