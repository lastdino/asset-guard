<div>
    <flux:modal wire:model="open">
        <flux:heading size="md">チェックリストまとめ実施</flux:heading>

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

            <div class="rounded border p-2 dark:border-zinc-700">
                <div class="text-sm text-zinc-500">期日までの未実施項目</div>
                <div class="mt-2 grid gap-3">
                    @forelse($forms as $itemId => $form)
                        <div class="rounded border p-2 dark:border-zinc-700">
                            <div class="font-medium">{{$form['name']}}</div>
                            <div class="flex flex-row gap-2">
                                <div class="flex-none">
                                    @foreach($form['media'] as $media)
                                        @php($url = URL::temporarySignedRoute(config('asset-guard.routes.prefix').'.inspections.items.media', now()->addMinutes(10), ['media' => $media->id]))
                                        <div class="" wire:key="media-{{ $media->id }}">
                                            <img src="{{$url}}" alt="photo" class="h-32 rounded object-cover border dark:border-zinc-700" />
                                        </div>
                                    @endforeach
                                </div>
                                <div class="w-full">
                                    @if(($form['method'] ?? null) === 'boolean')
                                        <flux:radio.group wire:model="forms.{{ $itemId }}.result" variant="segmented">
                                            <flux:radio label="{{ __('asset-guard::inspections.pass') }}" value="Pass"/>
                                            <flux:radio label="{{ __('asset-guard::inspections.fail') }}" value="Fail"/>
                                        </flux:radio.group>
                                        @error('forms.'.$itemId.'.result') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                                    @endif

                                    @if(($form['method'] ?? null) === 'number')
                                        <div class="mt-2 grid gap-1">
                                            <flux:input type="number" step="0.0001" wire:model.live="forms.{{ $itemId }}.number" placeholder="実測値" />
                                            <div class="text-xs text-zinc-500">許容範囲: {{ $form['min'] ?? '—' }} 〜 {{ $form['max'] ?? '—' }}</div>
                                            @error('forms.'.$itemId.'.number') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                                        </div>
                                    @endif

                                    @if(($form['method'] ?? null) === 'text')
                                        <flux:textarea wire:model.defer="forms.{{ $itemId }}.text" placeholder="結果のメモ" />
                                        @error('forms.'.$itemId.'.text') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                                    @endif

                                    @if(($form['method'] ?? null) === 'select')
                                        <flux:select wire:model.defer="forms.{{ $itemId }}.select">
                                            <option value="">選択してください...</option>
                                            @foreach(($form['options'] ?? []) as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </flux:select>
                                        @error('forms.'.$itemId.'.select') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
                                    @endif

                                    <flux:input class="mt-2" placeholder="備考（任意）" wire:model.defer="forms.{{ $itemId }}.note" />
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-zinc-500">対象項目はありません。</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('open', false)">キャンセル</flux:button>
            <flux:button variant="subtle" wire:click="saveDraftAll" wire:loading.attr="disabled">
                <span wire:loading.remove>下書き保存</span>
                <span wire:loading>保存中...</span>
            </flux:button>
            <flux:button variant="primary" wire:click="finalizeAll" wire:loading.attr="disabled">
                <span wire:loading.remove>確定して保存</span>
                <span wire:loading>保存中...</span>
            </flux:button>
        </div>
    </flux:modal>
</div>
