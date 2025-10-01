<div class="grid gap-3">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <flux:heading size="sm">故障・修理</flux:heading>
            <flux:select label="ステータス" wire:model.live="statusFilter">
                <option value="All">全て</option>
                <option value="Waiting">受付</option>
                <option value="InProgress">対応中</option>
                <option value="Completed">完了</option>
            </flux:select>
        </div>
        <flux:button variant="primary" size="xs" wire:click="openCreate">報告</flux:button>
    </div>

    <div class="grid gap-2">
        @foreach($this->incidents as $x)
            <div class="rounded border p-3 dark:border-zinc-700">
                <div class="flex items-center justify-between text-sm">
                    <div class="text-zinc-500">{{ optional($x->occurred_at)->format('Y-m-d H:i') }} / {{ optional($x->assignee)->name }}</div>
                    <div class="flex items-center gap-2">
                        <flux:badge>{{ $x->severity }}</flux:badge>
                        <flux:badge>{{ $x->status }}</flux:badge>
                        <flux:button size="xs" variant="subtle" wire:click="openEdit({{ $x->id }})">編集</flux:button>
                    </div>
                </div>
                <div class="mt-1 text-sm"><span class="text-zinc-500">事象:</span> {{ $x->event }}</div>
                @if($x->actions)
                    <div class="mt-1 text-sm"><span class="text-zinc-500">対処:</span> {{ $x->actions }}</div>
                @endif
                @php
                    $mins = $x->occurred_at ? $x->occurred_at->diffInMinutes($x->completed_at ?? now()) : null;
                @endphp
                @if(! is_null($mins))
                    @php
                        $d = intdiv($mins, 60 * 24);
                        $h = intdiv($mins % (60 * 24), 60);
                        $m = $mins % 60;
                        $label = $x->completed_at ? '期間' : '経過';
                    @endphp
                    <div class="mt-1 text-sm">
                        <span class="text-zinc-500">{{ $label }}:</span>
                        {{ $d }}日 {{ $h }}時間 {{ $m }}分
                        @if($x->completed_at)
                            <span class="text-zinc-400">(完了: {{ $x->completed_at->format('Y-m-d H:i') }})</span>
                        @endif
                    </div>
                @endif
                @php($attachments = $x->getMedia('attachments'))
                @if($attachments->isNotEmpty())
                    <div class="mt-2">
                        <div class="text-xs text-zinc-500">添付:</div>
                        <ul class="mt-1 flex flex-wrap gap-2">
                            @foreach($attachments as $media)
                                @php($url = URL::temporarySignedRoute(config('asset-guard.routes.prefix').'.incidents.download', now()->addMinutes(10), ['media' => $media->id]))
                                <li class="flex items-center gap-2">
                                    <a href="{{ $url }}" class="text-sm text-blue-600 hover:underline">
                                        {{ $media->file_name }} ({{ number_format($media->size / 1024, 1) }} KB)
                                    </a>
                                    <flux:button size="xs" variant="danger" wire:click="deleteAttachment({{ $media->id }})">削除</flux:button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endforeach
        @if($this->incidents->isEmpty())
            <div class="text-zinc-500">報告はありません。</div>
        @endif
    </div>

    <flux:modal wire:model="showModal">
        <flux:heading size="md">故障・修理の報告</flux:heading>
        <div class="mt-3 grid gap-3">
            <flux:input type="datetime-local" label="発生日時" wire:model.defer="form.occurred_at" />
            <flux:select label="担当者" wire:model.defer="form.assignee_id">
                <option value="">選択してください</option>
                @foreach($this->assigneeOptions as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </flux:select>
            <flux:textarea label="事象" wire:model.defer="form.event" rows="3" />
            <flux:textarea label="対処" wire:model.defer="form.actions" rows="4" />
            <flux:select label="ステータス" wire:model.defer="form.status">
                <option value="Waiting">受付</option>
                <option value="InProgress">対応中</option>
                <option value="Completed">完了</option>
            </flux:select>
            <flux:select label="重大度" wire:model.defer="form.severity">
                <option value="Low">低</option>
                <option value="Medium">中</option>
                <option value="High">高</option>
                <option value="Critical">緊急</option>
            </flux:select>
            <flux:input type="file" wire:model="files" multiple />
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showModal', false)">キャンセル</flux:button>
            <flux:button variant="primary" wire:click="save">保存</flux:button>
        </div>
    </flux:modal>
</div>
