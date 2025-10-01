<flux:modal wire:model="open" class="md:w-[720px]" :dismissible="true">
    <div class="flex items-start justify-between">
        <flux:heading size="lg">
            {{ __('asset-guard::inspections.detail.title') }}
        </flux:heading>
    </div>

    @if($inspection)
        <div class="mt-4 grid gap-2 text-sm">
            <div>
                <span class="text-zinc-500">{{ __('asset-guard::inspections.detail.performed_at') }}:</span>
                {{ optional($inspection->performed_at)->format('Y-m-d H:i') }}
            </div>
            @if($inspection->performer)
                <div>
                    <span class="text-zinc-500">{{ __('asset-guard::inspections.detail.performer') }}:</span>
                    {{ $inspection->performer->name }}
                </div>
            @endif
        </div>

        <div class="mt-6">
            <flux:heading size="sm">{{ __('asset-guard::inspections.detail.items') }}</flux:heading>
            <div class="mt-2 divide-y">
                @foreach($inspection->results as $result)
                    <div class="py-3 grid gap-1">
                        <div class="font-medium">
                            {{ $result->item?->name ?? __('asset-guard::inspections.detail.unknown_item') }}
                        </div>
                        <div class="text-sm text-zinc-700 dark:text-zinc-300">
                            <span class="text-zinc-500">{{ __('asset-guard::inspections.detail.result') }}:</span>
                            {{ $result->value_label ?? $result->value ?? '-' }}
                        </div>
                        @if($result->note)
                            <div class="text-sm text-zinc-700 dark:text-zinc-300">
                                <span class="text-zinc-500">{{ __('asset-guard::inspections.detail.note') }}:</span>
                                {{ $result->note }}
                            </div>
                        @endif
                        @if(method_exists($result, 'getMedia'))
                            @php(
                                $attachments = collect()
                                    ->merge($result->getMedia('attachments') ?? [])
                                    ->merge($result->getMedia('photos') ?? [])
                            )
                            @if($attachments->count())
                                <div class="mt-2 grid gap-2">
                                    <div class="text-xs text-zinc-500">{{ __('asset-guard::inspections.detail.attachments') }}</div>

                                    <div class="flex flex-wrap gap-2">
                                        @foreach($attachments as $media)
                                            @php($mime = $media->mime_type ?? null)
                                            @php($isImage = $mime && str_starts_with($mime, 'image/'))

                                            @if($isImage)
                                                @php($url = URL::temporarySignedRoute(config('asset-guard.routes.prefix').'.inspections.items.media', now()->addMinutes(10), ['media' => $media->id]))
                                                <a href="{{ $url }}" target="_blank" rel="noopener" class="block">
                                                    <img
                                                        src="{{ $url }}"
                                                        alt="{{ $media->file_name }}"
                                                        class="h-20 w-20 object-cover rounded border"
                                                    />
                                                </a>
                                            @else
                                                @php($url = URL::temporarySignedRoute(config('asset-guard.routes.prefix').'.inspections.results.download', now()->addMinutes(10), ['media' => $media->id]))
                                                <a
                                                    href="{{ $url }}"
                                                    download
                                                    class="inline-flex items-center gap-2 rounded border px-2 py-1 text-xs hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                                >
                                                    <span class="inline-block h-2 w-2 rounded-full bg-zinc-400"></span>
                                                    <span class="truncate max-w-[220px]" title="{{ $media->file_name }}">{{ $media->file_name }}</span>
                                                    <span class="text-zinc-500">{{ __('asset-guard::inspections.detail.download') }}</span>
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <p class="mt-4 text-sm text-zinc-500">{{ __('asset-guard::inspections.detail.not_found') }}</p>
    @endif

    <div class="mt-6 flex justify-end">
        <flux:button variant="subtle" wire:click="close">{{ __('asset-guard::common.close') }}</flux:button>
    </div>
</flux:modal>
