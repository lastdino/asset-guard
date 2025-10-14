<div class="p-6 space-y-4">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route(config('asset-guard.routes.prefix').'.dashboard.index') }}" icon="home" />
        <flux:breadcrumbs.item>{{ __('asset-guard::incidents.index.title') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <h1 class="text-xl font-semibold">{{ __('asset-guard::incidents.index.title') }}</h1>
            <div class="flex flex-wrap items-center gap-2">
                <flux:input class="max-w-60" wire:model.live.debounce.300ms="search" :placeholder="__('asset-guard::common.search')" />
                <flux:select wire:model.live="status" class="max-w-60">
                    <option value="">{{ __('asset-guard::incidents.filters.status.all') }}</option>
                    <option value="Waiting">{{ __('asset-guard::incidents.status.waiting') }}</option>
                    <option value="InProgress">{{ __('asset-guard::incidents.status.in_progress') }}</option>
                    <option value="Completed">{{ __('asset-guard::incidents.status.completed') }}</option>
                </flux:select>
                <flux:select wire:model.live="severity" class="max-w-60">
                    <option value="">{{ __('asset-guard::incidents.filters.severity.all') }}</option>
                    <option value="Low">{{ __('asset-guard::incidents.severity.low') }}</option>
                    <option value="Medium">{{ __('asset-guard::incidents.severity.medium') }}</option>
                    <option value="High">{{ __('asset-guard::incidents.severity.high') }}</option>
                    <option value="Critical">{{ __('asset-guard::incidents.severity.critical') }}</option>
                </flux:select>

                <flux:input class="max-w-48" wire:model.live.debounce.300ms="assetName" :placeholder="__('asset-guard::incidents.filters.asset_name')" />
                <flux:input class="max-w-40" wire:model.live.debounce.300ms="assetCode" :placeholder="__('asset-guard::incidents.filters.asset_code')" />

                <flux:select wire:model.live="perPage" class="max-w-60">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </flux:select>
                <flux:button variant="primary" wire:click="$refresh">{{ __('asset-guard::common.refresh') }}</flux:button>
            </div>
        </div>

        <div class="rounded-md border border-black/10 overflow-hidden bg-white dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800">
                        <tr>
                            <th class="px-4 py-3">
                                <button wire:click="sortBy('id')" class="font-medium hover:underline">{{ __('asset-guard::incidents.columns.id') }}</button>
                            </th>
                            <th class="px-4 py-3">
                                <button wire:click="sortBy('event')" class="font-medium hover:underline">{{ __('asset-guard::incidents.columns.title') }}</button>
                            </th>
                            <th class="px-4 py-3">
                                <button wire:click="sortBy('status')" class="font-medium hover:underline">{{ __('asset-guard::incidents.columns.status') }}</button>
                            </th>
                            <th class="px-4 py-3">
                                <button wire:click="sortBy('severity')" class="font-medium hover:underline">{{ __('asset-guard::incidents.columns.severity') }}</button>
                            </th>
                            <th class="px-4 py-3">
                                <button wire:click="sortBy('occurred_at')" class="font-medium hover:underline">{{ __('asset-guard::incidents.columns.occurred_at') }}</button>
                            </th>
                            <th class="px-4 py-3">{{ __('asset-guard::incidents.columns.reporter') }}</th>
                            <th class="px-4 py-3">{{ __('asset-guard::incidents.columns.asset') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('asset-guard::common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->incidents as $incident)
                            <tr class="border-t border-black/5 hover:bg-zinc-50/60 dark:hover:bg-zinc-800/60">
                                <td class="px-4 py-3">#{{ $incident->id }}</td>
                                <td class="px-4 py-3">{{ $incident->event }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-zinc-100 dark:bg-zinc-800">
                                        {{ __('asset-guard::incidents.status.'.Str::of($incident->status)->snake()->lower()) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ __('asset-guard::incidents.severity.'.Str::of($incident->severity)->snake()->lower()) }}</td>
                                <td class="px-4 py-3">{{ optional($incident->occurred_at)->locale(app()->getLocale())->translatedFormat('Y年n月j日 H:i') }}</td>
                                <td class="px-4 py-3">{{ $incident->assignee_name ?? optional($incident->assignee)->name }}</td>
                                <td class="px-4 py-3">{{ optional($incident->asset)->name }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <flux:button variant="subtle" wire:click="openView({{ $incident->id }})">{{ __('asset-guard::common.view') }}</flux:button>
                                        <flux:button variant="ghost" wire:click="openEdit({{ $incident->id }})">{{ __('asset-guard::common.edit') }}</flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-zinc-500">{{ __('asset-guard::incidents.index.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-black/10">
                {{ $this->incidents->links() }}
            </div>
        </div>

        <div wire:loading class="text-sm text-zinc-500">{{ __('asset-guard::common.loading') }}</div>

    <flux:modal wire:model="showViewModal">
        @if($selectedId)
            @php($x = \Lastdino\AssetGuard\Models\AssetGuardIncident::with(['asset','assignee'])->find($selectedId))
            @if($x)
                <flux:heading size="md">{{ __('asset-guard::incidents.modal.view_title') }}</flux:heading>
                <div class="mt-3 grid gap-2 text-sm">
                    <div><span class="text-zinc-500">{{ __('asset-guard::incidents.columns.asset') }}:</span> {{ optional($x->asset)->name }}</div>
                    <div><span class="text-zinc-500">{{ __('asset-guard::incidents.columns.title') }}:</span> {{ $x->event }}</div>
                    <div><span class="text-zinc-500">{{ __('asset-guard::incidents.columns.status') }}:</span> {{ $x->status }}</div>
                    <div><span class="text-zinc-500">{{ __('asset-guard::incidents.columns.severity') }}:</span> {{ $x->severity }}</div>
                    <div><span class="text-zinc-500">{{ __('asset-guard::incidents.columns.occurred_at') }}:</span> {{ optional($x->occurred_at)->format('Y-m-d H:i') }}</div>
                    <div><span class="text-zinc-500">{{ __('asset-guard::incidents.columns.reporter') }}:</span> {{ $x->assignee_name ?? optional($x->assignee)->name }}</div>
                    @if($x->actions)
                        <div><span class="text-zinc-500">{{ __('asset-guard::incidents.columns.actions') }}:</span> {{ $x->actions }}</div>
                    @endif
                </div>
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
                <div class="mt-4 flex justify-end">
                    <flux:button variant="ghost" wire:click="$set('showViewModal', false)">{{ __('asset-guard::common.close') }}</flux:button>
                </div>
            @endif

        @endif
    </flux:modal>

    <flux:modal wire:model="showEditModal">
        <flux:heading size="md">{{ __('asset-guard::incidents.modal.edit_title') }}</flux:heading>
        <div class="mt-3 grid gap-3">
            <flux:input type="datetime-local" :label="__('asset-guard::incidents.columns.occurred_at')" wire:model.defer="form.occurred_at" />
            <flux:select :label="__('asset-guard::incidents.columns.reporter')" wire:model.defer="form.assignee_id">
                <option value="">{{ __('asset-guard::common.select') }}</option>
                @foreach(\App\Models\User::query()->orderBy('name')->get() as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </flux:select>
            <flux:textarea :label="__('asset-guard::incidents.columns.title')" wire:model.defer="form.event" rows="3" />
            <flux:textarea :label="__('asset-guard::incidents.columns.actions')" wire:model.defer="form.actions" rows="4" />
            <flux:select :label="__('asset-guard::incidents.columns.status')" wire:model.defer="form.status">
                <option value="Waiting">{{ __('asset-guard::incidents.status.waiting') }}</option>
                <option value="InProgress">{{ __('asset-guard::incidents.status.in_progress') }}</option>
                <option value="Completed">{{ __('asset-guard::incidents.status.completed') }}</option>
            </flux:select>
            <flux:select :label="__('asset-guard::incidents.columns.severity')" wire:model.defer="form.severity">
                <option value="Low">{{ __('asset-guard::incidents.severity.low') }}</option>
                <option value="Medium">{{ __('asset-guard::incidents.severity.medium') }}</option>
                <option value="High">{{ __('asset-guard::incidents.severity.high') }}</option>
                <option value="Critical">{{ __('asset-guard::incidents.severity.critical') }}</option>
            </flux:select>
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="$set('showEditModal', false)">{{ __('asset-guard::common.cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="save">{{ __('asset-guard::common.save') }}</flux:button>
        </div>
    </flux:modal>

</div>
