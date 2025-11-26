<div class="p-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route(config('asset-guard.routes.prefix').'.dashboard.index') }}" icon="home" />
        <flux:breadcrumbs.item>保全計画</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">{{ __('asset-guard::maintenance_plans.title') }}</h1>
        <div class="flex gap-2">
            <flux:button variant="ghost" href="{{ route(config('asset-guard.routes.prefix').'.maintenance-plans.index') }}">{{ __('asset-guard::maintenance_plans.refresh') }}</flux:button>
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
                    保守リスト：{{ __('asset-guard::maintenance_plans.total', ['count' => $this->lists->count()]) }}
                </div>
                <div class="divide-y divide-neutral-200/70 dark:divide-neutral-700/70">
                    @forelse ($this->lists as $list)
                        <div class="py-3">
                            <div class="font-medium">
                                <a class="text-blue-600 hover:underline"
                                   href=""
                                   wire:click.prevent="openShow({{ $list->id }})"
                                >{{ $list->name }}</a>
                            </div>
                            <div class="text-sm text-neutral-600 dark:text-neutral-300">
                                {{ __('asset-guard::maintenance_plans.asset') }}: {{ optional($list->asset)->name ?? '#'.$list->asset_id }}
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-neutral-500">{{ __('asset-guard::maintenance_plans.no_plans') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-span-12 lg:col-span-8">
            <div id="maintenance-calendar" x-data="initCalendar" x-bind:data-pre-use="{{ $viewingList && $viewingList->require_before_activation ? 'true' : 'false' }}" wire:ignore class="bg-white dark:bg-neutral-900 rounded p-4"></div>
        </div>
    </div>



    {{-- Show modal --}}
    <flux:modal wire:model="showShow" class="w-2xl">
        <flux:heading size="md">{{ __('asset-guard::maintenance_plans.title') }}</flux:heading>
        @if($viewingList)
            <div class="space-y-3">
                <div class="text-sm text-neutral-600 dark:text-neutral-300">
                    {{ __('asset-guard::maintenance_plans.asset') }}:
                    {{ optional($viewingList->asset)->name ?? ('#'.$viewingList->asset_id) }}
                </div>
                <div class="text-sm text-neutral-600 dark:text-neutral-300">
                    {{ __('asset-guard::maintenance_plans.checklist') }}: {{ $viewingList->name }}
                </div>


                <div class="mt-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="font-medium">{{ __('asset-guard::inspections.upcoming') ?? 'Upcoming' }}</div>
                        @if($viewingListId && (! $viewingList?->require_before_activation))
                            <flux:button variant="filled" size="xs" wire:click="openPlanCreate({{ $viewingListId }})">
                                {{ __('asset-guard::plans.add') }}
                            </flux:button>
                        @endif
                    </div>
                    <div class="grid gap-2">
                        @if($viewingList->plans->count() > 0)
                            @foreach($viewingList->plans as $plan)
                                <div class="bg-white dark:bg-neutral-900 rounded p-4">
                                    <div class="flex items-center justify-between">
                                        <div class="font-medium">{{ $plan->title }}</div>
                                        <div class="flex items-center gap-2">
                                            <flux:button variant="ghost" size="xs" wire:click="openPlanEdit({{ $plan->id }})">
                                                {{ __('asset-guard::common.edit') }}
                                            </flux:button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="closeModals">{{ __('asset-guard::common.back') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Create/Edit modal --}}
    <flux:modal wire:model="showPlan">
        @if ($this->editingPlanId)
            <flux:heading size="md">{{ __('asset-guard::maintenance_plans.edit_title') }}</flux:heading>
        @else
            <flux:heading size="md">{{ __('asset-guard::maintenance_plans.new') }}</flux:heading>
        @endif
        {{-- Inline form (create) --}}
        <div class="">
            <div class="space-y-4 bg-white dark:bg-neutral-900 rounded p-4">
                <flux:select wire:model.live="form.asset_id" label="{{__('asset-guard::maintenance_plans.asset')}}" disabled>
                    <option >設備を選択</option>
                    @foreach ($this->assets as $asset)
                        <option value="{{ $asset->id }}">{{ $asset->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="form.checklist_id" label="{{__('asset-guard::maintenance_plans.checklist')}}">
                    @if (empty($this->form['asset_id']))
                        <option value="">{{ __('asset-guard::maintenance_plans.select_asset_first') }}</option>
                    @else
                        <option >チェックリストを選択</option>
                        @foreach ($this->lists->where('require_before_activation',false) as $cl)
                            <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                        @endforeach
                    @endif
                </flux:select>

                <flux:textarea wire:model.live="form.description" rows="4" label="{{__('asset-guard::maintenance_plans.description')}}"/>

                <flux:input type="number" min="0" max="30" wire:model.live="form.lead_time_days" label="{{__('asset-guard::maintenance_plans.lead_time_days')}}"/>


                <div class="grid grid-cols-2 gap-4">
                    <flux:input type="date" wire:model.live="form.scheduled_at" label="{{__('asset-guard::maintenance_plans.start_date')}}"/>
                    <flux:input type="date" wire:model.live="form.due_at" label="期限"/>
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
            <div class="mt-4 flex justify-between">
                @if ($this->editingPlanId)
                    <flux:modal.trigger name="delete-plan">
                        <flux:button variant="danger">{{ __('asset-guard::common.delete') }}</flux:button>
                    </flux:modal.trigger>
                @else
                    <div></div>
                @endif
                <div class="gap-2">
                    <flux:button variant="ghost" wire:click="closeModals">{{ __('asset-guard::common.back') }}</flux:button>
                    <flux:button variant="filled" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove>{{ __('asset-guard::common.save') }}</span>
                        <span wire:loading>{{ __('asset-guard::common.saving') }}</span>
                    </flux:button>
                </div>
            </div>

        </div>
    </flux:modal>

    {{-- Plan Delete confirm modal --}}
    <flux:modal name="delete-plan" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('asset-guard::plans.delete_title') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('asset-guard::plans.delete_confirm') }}
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost" >{{ __('asset-guard::common.cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger" wire:click="deletePlan" wire:loading.attr="disabled">{{ __('asset-guard::common.delete') }}</flux:button>
            </div>
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
                dateClick: info => {
                    $wire.openPlanCreateFromCalendar(info.dateStr);
                },
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
                        $wire.openPlanEdit(Id);
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
