<div>
    <flux:modal wire:model="open" size="xl">
        <flux:heading size="md">
            {{ __('asset-guard::inspections.perform') }}
        </flux:heading>

        <div class="mt-4 space-y-6">
            <div class="grid gap-4">
                <div class="flex flex-wrap items-center gap-3 text-sm text-neutral-600 dark:text-neutral-300">
                    @if ($mode === 'plan-batch')
                        <div>{{ __('asset-guard::inspections.mode') }}: <span class="font-medium">{{ __('asset-guard::inspections.scheduled') }}</span></div>
                        @if ($planId)
                            <div>#{{ $planId }}</div>
                        @endif
                    @elseif ($mode === 'preuse')
                        <div>{{ __('asset-guard::inspections.mode') }}: <span class="font-medium">{{ __('asset-guard::inspections.pre_use') }}</span></div>
                    @elseif ($mode === 'plan-single')
                        <div>{{ __('asset-guard::inspections.mode') }}: <span class="font-medium">{{ __('asset-guard::inspections.scheduled') }}</span> <span class="ml-2 text-xs">single</span></div>
                        @if ($planId)
                            <div>#{{ $planId }}</div>
                        @endif
                    @endif
                </div>

                <div class="grid gap-3">
                    <div class="grid md:grid-cols-2 gap-3">
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
                    </div>
                </div>
            </div>

            @if ($mode === 'preuse' && $selectingPreuse)
                <div class="grid gap-3">
                    <flux:field :label="__('asset-guard::inspections.select_pre_use_checklist')">
                        <div class="grid gap-2">
                            @foreach ($preuseOptions as $opt)
                                <flux:button variant="subtle" wire:click="selectChecklist({{ (int) $opt['id'] }})">
                                    {{ $opt['name'] }}
                                </flux:button>
                            @endforeach
                        </div>
                    </flux:field>
                    <div class="flex justify-end">
                        <flux:button variant="ghost" wire:click="$set('open', false)">{{ __('asset-guard::common.cancel') }}</flux:button>
                    </div>
                </div>
            @else
                <div class="rounded border p-3 dark:border-neutral-800">
                    <div class="grid gap-4">
                        @foreach ($forms as $itemId => $form)
                            <div class="rounded bg-neutral-50 dark:bg-neutral-900 p-3">
                                <div class="font-medium">{{ $form['name'] ?? '#' . $itemId }}</div>
                                <div class="mt-2 grid md:grid-cols-2 gap-3">
                                    @if (!empty($form['media']))
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($form['media'] as $m)
                                                <img src="{{$this->temporaryURL($m['id'])}}" alt="photo" class="h-28 w-full rounded object-cover border dark:border-zinc-700" />
                                            @endforeach
                                        </div>
                                    @endif
                                    @if (($form['method'] ?? null) === 'boolean')
                                        <flux:select :label="__('asset-guard::inspections.result')" wire:model.live="forms.{{ $itemId }}.result">
                                            <option value="Pass">{{ __('asset-guard::inspections.pass') }}</option>
                                            <option value="Fail">{{ __('asset-guard::inspections.fail') }}</option>
                                        </flux:select>
                                    @elseif (($form['method'] ?? null) === 'number')
                                        <flux:input type="number" step="any" :label="__('asset-guard::inspections.number')" wire:model.live="forms.{{ $itemId }}.number" />
                                        <div class="text-xs text-neutral-500 flex items-end">@if(!is_null($form['min'])) min: {{ $form['min'] }} @endif @if(!is_null($form['max'])) <span class="ml-2">max: {{ $form['max'] }}</span> @endif</div>
                                    @elseif (($form['method'] ?? null) === 'text')
                                        <flux:textarea :label="__('asset-guard::inspections.text')" wire:model.live="forms.{{ $itemId }}.text" />
                                    @elseif (($form['method'] ?? null) === 'select')
                                        <flux:select :label="__('asset-guard::inspections.select')" wire:model.live="forms.{{ $itemId }}.select">
                                            <flux:select.option value=""></flux:select.option>
                                            @foreach (($form['options'] ?? []) as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </flux:select>
                                    @endif

                                </div>
                                <div class="mt-2">
                                    <flux:textarea :label="__('asset-guard::inspections.note')" wire:model.live="forms.{{ $itemId }}.note" />
                                </div>


                                <div class="mt-2 grid md:grid-cols-2 gap-3">
                                    <flux:input
                                        type="file"
                                        multiple
                                        accept="image/*,application/pdf"
                                        wire:model="attachments.{{ $itemId }}"
                                        :label="__('asset-guard::inspections.attachments_label')"
                                    />
                                    @error('attachments.'.$itemId.'.*')
                                    <div class="text-sm text-red-600">{{ $message }}</div>
                                    @enderror
                                </div>


                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button variant="subtle" wire:click="$set('open', false)">{{ __('asset-guard::common.cancel') }}</flux:button>
                <flux:button variant="ghost" wire:click="saveDraftAll">{{ __('asset-guard::inspections.save_draft') }}</flux:button>
                <flux:button variant="primary" wire:click="finalizeAll">{{ __('asset-guard::inspections.save_and_finalize') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
