<div>
    <flux:modal wire:model="open">
        <flux:heading size="md">{{ __('asset-guard::inspections.perform_modal_title') }}</flux:heading>

        <div class="mt-3 grid gap-3">
            <div class="text-sm text-zinc-500">{{ __('asset-guard::inspections.target') }}: {{ $assetLabel }}</div>
            <div class="font-medium">{{ $itemName }}</div>

            <flux:select :label="__('asset-guard::inspections.inspector')" wire:model.defer="inspectorId">
                @foreach(\App\Models\User::orderBy('name')->get(['id','name']) as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </flux:select>

            <flux:select :label="__('asset-guard::inspections.co_inspectors')" multiple wire:model.defer="coInspectorIds">
                @foreach(\App\Models\User::orderBy('name')->get(['id','name']) as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </flux:select>
            <div class="flex flex-row gap-2">
                <div class="flex-none">
                    @foreach($medias as $media)
                        @php($url = URL::temporarySignedRoute(config('asset-guard.routes.prefix').'.inspections.items.media', now()->addMinutes(10), ['media' => $media->id]))
                        <div class="" wire:key="media-{{ $media->id }}">
                            <img src="{{$url}}" alt="photo" class="h-32 rounded object-cover border dark:border-zinc-700" />
                        </div>
                    @endforeach
                </div>
                <div class="w-full">
                    @if($method === 'boolean')
                        <flux:radio.group wire:model="result" variant="segmented">
                            <flux:radio label="{{ __('asset-guard::inspections.pass') }}" value="Pass"/>
                            <flux:radio label="{{ __('asset-guard::inspections.fail') }}" value="Fail"/>
                        </flux:radio.group>
                        @error('result') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                    @endif

                    @if($method === 'number')
                        <div class="grid gap-1">
                            <flux:input type="number" step="0.0001" wire:model.live="number" :placeholder="__('asset-guard::inspections.measured_value')" />
                            <div class="text-xs text-zinc-500">{{ __('asset-guard::inspections.tolerance') }}: {{ $minValue ?? '—' }} 〜 {{ $maxValue ?? '—' }}</div>
                            @error('number') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    @if($method === 'text')
                        <flux:textarea wire:model.defer="text" :placeholder="__('asset-guard::inspections.result_note')" />
                        @error('text') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                    @endif

                    @if($method === 'select')
                        <flux:select wire:model.defer="select">
                            <option value="">{{ __('asset-guard::inspections.select_placeholder') }}</option>
                            @foreach($options as $opt)
                                <option value="{{ $opt }}">{{ $opt }}</option>
                            @endforeach
                        </flux:select>
                        @error('select') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                    @endif
                </div>
            </div>




            <flux:textarea wire:model.defer="note" :placeholder="__('asset-guard::inspections.note_placeholder')" />

            <div class="grid gap-2">
                <flux:input type="file" multiple wire:model="attachments" accept="image/*,application/pdf" :label="__('asset-guard::inspections.attachments_label')" />
                @error('attachments.*')
                    <div class="text-xs text-red-600">{{ $message }}</div>
                @enderror

                @if($attachments)
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($attachments as $i => $file)
                            <div class="flex items-center gap-2 rounded border p-2 dark:border-zinc-700">
                                @if(str_starts_with($file->getMimeType(), 'image/'))
                                    <img src="{{ $file->temporaryUrl() }}" class="h-20 w-20 rounded object-cover" alt="preview" />
                                @else
                                    <div class="text-xs">{{ $file->getClientOriginalName() }}</div>
                                @endif
                                <flux:button size="xs" variant="ghost" wire:click="$set('attachments.{{ $i }}', null)">{{ __('asset-guard::inspections.remove_attachment') }}</flux:button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('open', false)">{{ __('asset-guard::common.cancel') }}</flux:button>
            <flux:button variant="subtle" wire:click="saveDraft" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('asset-guard::inspections.save_draft') }}</span>
                <span wire:loading>{{ __('asset-guard::inspections.saving') }}</span>
            </flux:button>
            <flux:button variant="primary" wire:click="finalize" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('asset-guard::inspections.save_and_finalize') }}</span>
                <span wire:loading>{{ __('asset-guard::inspections.saving') }}</span>
            </flux:button>
        </div>
    </flux:modal>
</div>
