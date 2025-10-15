<flux:modal wire:model="open">
    <flux:heading size="md">{{ __('asset-guard::inspections.perform_modal_title') }}</flux:heading>

    <div class="mt-3 grid gap-3">
        <div class="grid gap-2">
            <flux:select label="点検者" wire:model.defer="inspectorId">
                <option value=""></option>
                @foreach(\App\Models\User::orderBy('name')->get(['id','name']) as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </flux:select>
            <flux:select label="共同点検者（任意・複数）" multiple wire:model.defer="coInspectorIds">
                @foreach(\App\Models\User::orderBy('name')->get(['id','name']) as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </flux:select>
        </div>

        {{-- Render all checklist items similar to batch performer --}}
        <div class="grid gap-3 py-1">
            @forelse($form as $itemId => $row)
                <div class="rounded border p-2 dark:border-zinc-700">
                    <div class="font-medium">{{ $row['name'] }}</div>
                    <div class="flex flex-row gap-2">
                        <div class="flex-none">
                            @foreach($row['media'] as $media)
                                @php($url = URL::temporarySignedRoute(config('asset-guard.routes.prefix').'.inspections.items.media', now()->addMinutes(10), ['media' => $media->id]))
                                <div class="" wire:key="media-{{ $media->id }}">
                                    <img src="{{$url}}" alt="photo" class="h-32 rounded object-cover border dark:border-zinc-700" />
                                </div>
                            @endforeach
                        </div>
                        <div class="w-full">
                            @if(($row['method'] ?? null) === 'boolean')
                                <flux:radio.group wire:model="form.{{ $itemId }}.result" variant="segmented">
                                    <flux:radio label="{{ __('asset-guard::inspections.pass') }}" value="Pass"/>
                                    <flux:radio label="{{ __('asset-guard::inspections.fail') }}" value="Fail"/>
                                </flux:radio.group>
                                @error('form.'.$itemId.'.result') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                            @endif

                            @if(($row['method'] ?? null) === 'number')
                                <div class="mt-2 grid gap-1">
                                    <flux:input type="number" step="0.0001" wire:model.live="form.{{ $itemId }}.number" placeholder="実測値" />
                                    <div class="text-xs text-zinc-500">許容範囲: {{ $row['min'] ?? '—' }} 〜 {{ $row['max'] ?? '—' }}</div>
                                    @error('form.'.$itemId.'.number') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                                </div>
                            @endif

                            @if(($row['method'] ?? null) === 'text')
                                <flux:textarea wire:model.defer="form.{{ $itemId }}.text" placeholder="結果のメモ" />
                                @error('form.'.$itemId.'.text') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                            @endif

                            @if(($row['method'] ?? null) === 'select')
                                <flux:select wire:model.defer="form.{{ $itemId }}.select">
                                    <option value="">選択してください...</option>
                                    @foreach(($row['options'] ?? []) as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </flux:select>
                                @error('form.'.$itemId.'.select') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                            @endif

                            <flux:input class="mt-2" placeholder="備考（任意）" wire:model.defer="form.{{ $itemId }}.note" />
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-zinc-500">対象項目はありません。</div>
            @endforelse
        </div>

        <flux:textarea :label="__('asset-guard::inspections.result_note')" wire:model.defer="note" rows="3" :placeholder="__('asset-guard::inspections.note_placeholder')" />
    </div>

    <div class="mt-4 flex justify-end gap-2">
        <flux:button variant="ghost" wire:click="$set('open', false)">{{ __('asset-guard::common.cancel') ?? 'キャンセル' }}</flux:button>
        <flux:button variant="subtle" wire:click="saveDraft" wire:loading.attr="disabled">
            <span wire:loading.remove>{{ __('asset-guard::inspections.save_draft') }}</span>
            <span wire:loading>{{ __('asset-guard::inspections.saving') }}</span>
        </flux:button>
        <flux:button variant="primary" wire:click="finalizeAll" wire:loading.attr="disabled">
            <span wire:loading.remove>{{ __('asset-guard::inspections.save_and_finalize') }}</span>
            <span wire:loading>{{ __('asset-guard::inspections.saving') }}</span>
        </flux:button>
    </div>
</flux:modal>
