<div class="grid gap-4">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route(config('asset-guard.routes.prefix').'.dashboard.index') }}" icon="home" />
        <flux:breadcrumbs.item>設備一覧</flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <!-- ヘッダーフィルタと新規ボタン -->
    <div class="flex flex-wrap items-end gap-2">
        <!-- Global quick search (optional) -->
        <flux:input class="max-w-60" placeholder="{{ __('asset-guard::assets.quick_search') }}" wire:model.live.debounce.300ms="search" />

        <!-- Individual field searches -->
        <flux:input class="max-w-40" placeholder="{{ __('asset-guard::assets.search.code') }}" wire:model.live.debounce.300ms="searchCode" />
        <flux:input class="max-w-48" placeholder="{{ __('asset-guard::assets.search.name') }}" wire:model.live.debounce.300ms="searchName" />
        <flux:select class="max-w-44" wire:model.live.debounce.300ms="searchLocation">
            <option value="">{{ __('asset-guard::assets.search.location') }}</option>
            @foreach($this->locationOptions as $loc)
                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
            @endforeach
        </flux:select>
        <flux:input class="max-w-40" placeholder="{{ __('asset-guard::assets.search.serial') }}" wire:model.live.debounce.300ms="searchSerial" />
        <flux:input class="max-w-48" placeholder="{{ __('asset-guard::assets.search.fixed') }}" wire:model.live.debounce.300ms="searchFixed" />

        <flux:select class="max-w-40" wire:model.live="status">
            <option value="">{{ __('asset-guard::assets.filters.status.all') }}</option>
            <option value="Active">{{ __('asset-guard::assets.filters.status.Active') }}</option>
            <option value="UnderMaintenance">{{ __('asset-guard::assets.filters.status.UnderMaintenance') }}</option>
            <option value="Inactive">{{ __('asset-guard::assets.filters.status.Inactive') }}</option>
            <option value="Retired">{{ __('asset-guard::assets.filters.status.Retired') }}</option>
        </flux:select>

        <flux:select class="max-w-48" wire:model.live="assetTypeId">
            <option value="">{{ __('asset-guard::assets.fields.asset_type') }} — {{ __('asset-guard::common.all') }}</option>
            @foreach($this->typeOptions as $t)
                <option value="{{ $t->id }}">{{ $t->name }}</option>
            @endforeach
        </flux:select>

        <flux:spacer />
        <flux:button variant="primary" wire:click="openCreate">{{ __('asset-guard::assets.actions.new') }}</flux:button>
    </div>

    <!-- 一覧 -->
    <div class="grid gap-2">
        @foreach($this->assets as $asset)
            <div class="rounded border p-3 dark:border-zinc-700" wire:key="asset-{{ $asset->id }}" >
                <div class="flex items-center justify-between" >
                    <div class="flex items-center gap-3 w-full" wire:click="openDetail({{ $asset->id }})">
                        @php($photos = $asset->getMedia('photos'))
                        @if($photos->isNotEmpty())
                            <img src="{{ $this->temporaryURL($photos->first()->id) }}" alt="{{ $asset->name }}" class="h-10 w-10 rounded object-cover border dark:border-zinc-700" />
                        @else
                            <flux:icon name="computer-desktop" />
                        @endif
                        <div>
                            <div class="font-medium">{{ $asset->code }} — {{ $asset->manufacturer }} — {{ $asset->name }}</div>
                            <div class="text-sm text-zinc-500">{{ optional($asset->location)->name }} / {{ $asset->serial_no }} / {{ $asset->fixed_asset_no }} / {{ $asset->assetType->name }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @php($statusColor = [
                            'Active' => 'green',
                            'UnderMaintenance' => 'amber',
                            'Inactive' => 'zinc',
                            'Retired' => 'slate',
                        ][$asset->status] ?? 'zinc')

                        <flux:badge color="{{ $statusColor }}">
                            {{ __('asset-guard::assets.filters.status.' . $asset->status) ?? $asset->status }}
                        </flux:badge>

                        @php($dueCount = (int) data_get($this->dueOccurrencesCountForList, $asset->id, 0))
                        @if($dueCount > 0)
                            <flux:badge size="xs" color="red" title="{{ trans_choice('asset-guard::inspections.pending_count', $dueCount, ['count' => $dueCount]) }}">
                                {{ __('asset-guard::inspections.pending_short') }}: {{ $dueCount }}
                            </flux:badge>
                        @endif

                        @if(data_get($this->preUseRequiredForList, $asset->id, false))
                            <flux:button size="xs" variant="primary" wire:click="startPreUseInspection({{ $asset->id }})">
                                {{ __('asset-guard::inspections.start_pre_use') }}
                            </flux:button>
                        @endif

                    </div>
                </div>

                @if($asset->children->isNotEmpty())
                    <div class="mt-2 grid gap-1">
                        @foreach($asset->children as $child)
                            <div class="flex items-center justify-between rounded bg-zinc-50 p-2 text-sm dark:bg-zinc-800" wire:key="child-{{ $child->id }}">
                                <div>{{ $child->code }} — {{ $child->name }}</div>
                                @php($childStatusColor = [
                                    'Active' => 'green',
                                    'UnderMaintenance' => 'amber',
                                    'Inactive' => 'zinc',
                                    'Retired' => 'slate',
                                ][$child->status] ?? 'zinc')

                                <flux:badge size="xs" color="{{ $childStatusColor }}">
                                    {{ __('asset-guard::assets.filters.status.' . $child->status) ?? $child->status }}
                                </flux:badge>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <!-- 新規作成モーダル -->
    <flux:modal wire:model="showCreate">
        <flux:heading size="md">設備・機器の新規登録</flux:heading>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 py-3">
            <flux:input label="固有ID (code)" wire:model.defer="form.code" required />
            <flux:input label="名称" wire:model.defer="form.name" required />
            <flux:select label="ステータス" wire:model.defer="form.status">
                <option value="Active">稼働</option>
                <option value="UnderMaintenance">保守中</option>
                <option value="Inactive">停止</option>
                <option value="Retired">廃止</option>
            </flux:select>
            <flux:input label="シリアル" wire:model.defer="form.serial_no" />
            <flux:input label="固定資産番号" wire:model.defer="form.fixed_asset_no" />
            <flux:select label="設置場所" wire:model.defer="form.location_id">
                <option value="">選択してください</option>
                @foreach($this->locationOptions as $loc)
                    <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                @endforeach
            </flux:select>
            <flux:input type="date" label="設置日" wire:model.defer="form.installed_at" />
            <flux:input label="メーカー" wire:model.defer="form.manufacturer" />
            <flux:select label="{{ __('asset-guard::assets.fields.asset_type') }}" wire:model.defer="form.asset_type_id">
                <option value="">{{ __('asset-guard::common.select') }}</option>
                @foreach($this->typeOptions as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </flux:select>
            <flux:input label="付属元 (parent_id)" wire:model.defer="form.parent_id" placeholder="ID" />
        </div>
        <flux:textarea label="仕様 (spec)" wire:model.defer="form.spec" rows="4" />

        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="showCreate = false">{{ __('asset-guard::assets.actions.cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('asset-guard::assets.actions.save') }}</span>
                <span wire:loading>{{ __('asset-guard::assets.actions.saving') }}</span>
            </flux:button>
        </div>
    </flux:modal>

    <!-- 編集モーダル -->
    <flux:modal wire:model="showEdit">
        <flux:heading size="md">設備・機器の編集</flux:heading>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 py-3">
            <flux:input label="固有ID (code)" wire:model.defer="form.code" required />
            <flux:input label="名称" wire:model.defer="form.name" required />
            <flux:select label="ステータス" wire:model.defer="form.status">
                <option value="Active">稼働</option>
                <option value="UnderMaintenance">保守中</option>
                <option value="Inactive">停止</option>
                <option value="Retired">廃止</option>
            </flux:select>
            <flux:input label="シリアル" wire:model.defer="form.serial_no" />
            <flux:input label="固定資産番号" wire:model.defer="form.fixed_asset_no" />
            <flux:select label="設置場所" wire:model.defer="form.location_id">
                <option value="">選択してください</option>
                @foreach($this->locationOptions as $loc)
                    <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                @endforeach
            </flux:select>
            <flux:input type="date" label="設置日" wire:model.defer="form.installed_at" />
            <flux:input label="メーカー" wire:model.defer="form.manufacturer" />
            <flux:select label="{{ __('asset-guard::assets.fields.asset_type') }}" wire:model.defer="form.asset_type_id">
                <option value="">{{ __('asset-guard::common.select') }}</option>
                @foreach($this->typeOptions as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </flux:select>
            <flux:input label="付属元 (parent_id)" wire:model.defer="form.parent_id" placeholder="ID" />
        </div>
        <flux:textarea label="仕様 (spec)" wire:model.defer="form.spec" rows="4" />

        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="showEdit = false">{{ __('asset-guard::assets.actions.cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="update" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('asset-guard::assets.actions.update') }}</span>
                <span wire:loading>{{ __('asset-guard::assets.actions.updating') }}</span>
            </flux:button>
        </div>
    </flux:modal>

    <!-- 親を廃止する際の選択モーダル（子の扱い） -->
    <flux:modal wire:model="showRetireParent">
        <flux:heading size="md">親設備を廃止</flux:heading>
        <p class="mt-1 text-sm text-zinc-500">子設備の扱いを選択してください。</p>
        <div class="mt-3 grid gap-2">
            <flux:radio.group wire:model="retireChildrenStrategy" label="Select your payment method">
                <flux:radio value="cascade" label="子も廃止（Retired に揃える）" />
                <flux:radio value="detach" label="子を切り離す（親子関係を解除）" />
                <flux:radio value="keep" label="子は維持（状態を変更しない）" />
            </flux:radio.group>
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <flux:button variant="ghost" wire:click="showRetireParent = false">キャンセル</flux:button>
            <flux:button variant="danger" wire:click="retireParentCommit" wire:loading.attr="disabled">
                <span wire:loading.remove>廃止する</span>
                <span wire:loading>処理中...</span>
            </flux:button>
        </div>
    </flux:modal>

    <!-- 詳細モーダル：設備サマリ（常時） + 点検履歴 / 点検項目 -->
    <flux:modal wire:model="showDetail" variant="flyout" @close="closeDetail">
        <div class="flex items-center justify-between">
            <flux:heading size="md">設備詳細</flux:heading>
        </div>

        @if($selectedAsset)
            <!-- 編集 -->
            <div class="justify-self-end">
                <div class="flex items-center gap-2">
                    <flux:button size="xs" variant="ghost" wire:click="openEdit({{ $selectedAsset->id }})">{{ __('asset-guard::assets.edit') ?? '編集' }}</flux:button>

                    @if($this->preUseRequired)
                        <flux:button
                            size="xs"
                            variant="primary"
                            wire:click="startPreUseInspection({{ $selectedAsset->id }})"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove>{{ __('asset-guard::inspections.start_pre_use') }}</span>
                            <span wire:loading>{{ __('asset-guard::inspections.starting') }}</span>
                        </flux:button>
                    @else
                        <flux:tooltip text="{{ __('asset-guard::inspections.pre_use_not_required_today') }}">
                            <flux:button size="xs" variant="subtle" disabled>
                                {{ __('asset-guard::inspections.start_pre_use') }}
                            </flux:button>
                        </flux:tooltip>
                    @endif
                </div>
            </div>
            <!-- 常時表示: 設備サマリ（情報 + 写真） -->
            <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                <!-- 写真 -->
                <div class="justify-self-center w-full">
                    <!-- 旧: メタの写真URLは互換として残す -->
                    @php(
                        $photoUrl = data_get($selectedAsset->meta, 'photo_url')
                            ?? ($selectedAsset->photo_url ?? null)
                    )
                    @if($photoUrl && ($selectedAsset->getMedia('photos')->isEmpty()))
                        <img src="{{ $photoUrl }}" alt="設備写真" class="aspect-video h-32 w-auto rounded object-cover border dark:border-zinc-700" />
                    @endif

                    <!-- Spatie Media Library ギャラリー（プライベート） -->
                    @php($photos = $selectedAsset->getMedia('photos'))
                    <div class="grid gap-2">
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                            @forelse($photos as $media)
                                <div class="relative group" wire:key="media-{{ $media->id }}">
                                    <img src="{{$this->temporaryURL($media->id)}}" alt="photo" class="h-28 w-full rounded object-cover border dark:border-zinc-700" />
                                    <div class="absolute right-1 top-1 opacity-0 group-hover:opacity-100 transition">
                                        <flux:button size="xs" variant="danger" wire:click="deleteAssetImage({{ $media->id }})">削除</flux:button>
                                    </div>
                                </div>
                            @empty
                                <div class="text-sm text-zinc-500">写真はまだありません。</div>
                            @endforelse
                        </div>
                        @if($selectedAsset->getMedia('photos')->isEmpty())
                            <!-- アップロード -->
                            <div class="flex items-center gap-2">
                                <input type="file" multiple wire:model="assetImages" accept="image/jpeg,image/png" />
                                <flux:button variant="filled" wire:click="uploadAssetImages" wire:loading.attr="disabled">
                                    <span wire:loading.remove>アップロード</span>
                                    <span wire:loading>アップロード中...</span>
                                </flux:button>
                            </div>
                            @error('assetImages.*')
                            <div class="text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>
                </div>
                <!-- 情報 -->
                <div class="grid grid-cols-2 gap-1 text-sm">
                    <div class="">{{ $selectedAsset->name }}</div>
                    <div><span class="text-zinc-500">名称:</span> {{ $selectedAsset->name }}</div>
                    <div><span class="text-zinc-500">コード:</span> {{ $selectedAsset->code }}</div>
                    <div><span class="text-zinc-500">設置場所:</span> {{ optional($selectedAsset->location)->name ?? $selectedAsset->location ?? '-' }}</div>
                    <div><span class="text-zinc-500">ステータス:</span>
                        @php($statusColor = [
                            'Active' => 'green',
                            'UnderMaintenance' => 'amber',
                            'Inactive' => 'zinc',
                            'Retired' => 'slate',
                        ][$selectedAsset->status] ?? 'zinc')

                        <flux:badge color="{{ $statusColor }}" size="sm">
                            {{ __('asset-guard::assets.filters.status.' . $selectedAsset->status) ?? $selectedAsset->status }}
                        </flux:badge>
                    </div>
                    <div><span class="text-zinc-500">種別:</span> {{ $selectedAsset->assetType->name }}</div>
                    @if($selectedAsset->serial_no)
                        <div><span class="text-zinc-500">シリアル:</span> {{ $selectedAsset->serial_no }}</div>
                    @endif
                    @if($selectedAsset->fixed_asset_no)
                        <div><span class="text-zinc-500">固定資産番号:</span> {{ $selectedAsset->fixed_asset_no }}</div>
                    @endif
                    @if($selectedAsset->installed_at)
                        <div><span class="text-zinc-500">設置日:</span> {{ $selectedAsset->installed_at?->format('Y-m-d') }}</div>
                    @endif
                    @if($selectedAsset->manufacturer)
                        <div><span class="text-zinc-500">メーカー:</span> {{ $selectedAsset->manufacturer }}</div>
                    @endif
                </div>


            </div>

            <!-- タブボタン群（概要タブは廃止） -->
            <div class="mt-4 flex gap-2">
                <flux:button variant="subtle" wire:click="switchTab('inspections')" class="{{ $activeTab==='inspections' ? 'bg-zinc-100 dark:bg-zinc-800' : '' }}">点検履歴</flux:button>
                <flux:button variant="subtle" wire:click="switchTab('items')" class="{{ $activeTab==='items' ? 'bg-zinc-100 dark:bg-zinc-800' : '' }}">点検項目</flux:button>
                <flux:button variant="subtle" wire:click="switchTab('incidents')" class="{{ $activeTab==='incidents' ? 'bg-zinc-100 dark:bg-zinc-800' : '' }}">故障・修理</flux:button>
            </div>

            <!-- 点検履歴タブ -->
            @if($activeTab === 'inspections')
                <div class="mt-4">
                    <livewire:asset-guard.inspections.index :assetId="$selectedAssetId" />
                    @livewire('Lastdino\\AssetGuard\\Livewire\\AssetGuard\\Inspections\\ChecklistHistoryPanel', ['assetId' => $selectedAssetId])
                    @livewire('Lastdino\\AssetGuard\\Livewire\\AssetGuard\\Inspections\\Show')
                </div>
            @endif

            <!-- 点検項目タブ（CRUD） -->
            @if($activeTab === 'items')
                <div class="mt-4 grid gap-6">
                    <flux:button size="sm" variant="subtle" href="{{ route(config('asset-guard.routes.prefix').'.maintenance-plans.index', ['assetId' => $selectedAssetId]) }}">{{ __('asset-guard::maintenance_plans.manage_for_asset') }}</flux:button>
                    @livewire('Lastdino\\AssetGuard\\Livewire\\AssetGuard\\Inspections\\ChecklistPanel', ['assetId' => $selectedAssetId])
                    @livewire('Lastdino\\AssetGuard\\Livewire\\AssetGuard\\Inspections\\ChecklistItemsPanel', ['assetId' => $selectedAssetId])

                    <flux:separator />
                    @if($selectedAsset?->asset_type_id)
                        @livewire('Lastdino\\AssetGuard\\Livewire\\AssetGuard\\AssetTypes\\ChecklistManager', [
                            'assetTypeId' => $selectedAsset->asset_type_id,
                            'readonly' => true,
                        ])
                    @endif
                </div>
            @endif

            <!-- 故障・修理タブ -->
            @if($activeTab === 'incidents')
                <div class="mt-4">
                    @livewire('Lastdino\\AssetGuard\\Livewire\\AssetGuard\\Incidents\\IncidentPanel', ['assetId' => $selectedAssetId])
                </div>
            @endif
        @endif
    </flux:modal>

        <!-- Pre-use checklist selector modal (multiple plans) -->
        <flux:modal wire:model="showPreUseSelector">
            <flux:heading size="md">{{ __('asset-guard::inspections.select_pre_use_checklist') }}</flux:heading>
            <div class="mt-3 grid gap-2">
                @foreach ($selectorOptions as $opt)
                    <flux:button
                        variant="subtle"
                        wire:click="$dispatch('open-pre-use-performer', { assetId: {{ $selectorAssetId ?? 'null' }}, checklistId: {{ (int) $opt['id'] }} }); $set('showPreUseSelector', false)"
                    >
                        {{ $opt['name'] }}
                    </flux:button>
                @endforeach
            </div>
            <div class="mt-4 flex justify-end">
                <flux:button variant="ghost" wire:click="$set('showPreUseSelector', false)">{{ __('asset-guard::common.cancel') }}</flux:button>
            </div>
        </flux:modal>

        @livewire('asset-guard.inspections.pre-use-performer')

    </div>

