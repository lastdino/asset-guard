<?php

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Assets;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Lastdino\AssetGuard\Models\AssetGuardAsset;
use Lastdino\AssetGuard\Models\AssetGuardAssetType;
use Lastdino\AssetGuard\Models\AssetGuardLocation;
use Lastdino\AssetGuard\Models\AssetGuardMaintenancePlan;
use Lastdino\AssetGuard\Services\OperatingStatusService;
use Lastdino\AssetGuard\Services\PreUseInspectionGate;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    // Filters
    public string $search = '';

    public string $status = '';

    public ?int $assetTypeId = null;

    // Individual search fields
    public string $searchCode = '';

    public string $searchName = '';

    public string $searchLocation = '';

    public string $searchSerial = '';

    public string $searchFixed = '';

    // Modals
    public bool $showCreate = false;

    public bool $showEdit = false;

    public ?int $editingId = null;

    // Retire parent modal
    public bool $showRetireParent = false;

    public ?int $retireParentId = null;

    public string $retireChildrenStrategy = 'cascade'; // cascade|detach|keep

    // Form
    public array $form = [
        'code' => '',
        'name' => '',
        'status' => 'Active',
        'serial_no' => '',
        'fixed_asset_no' => '',
        'manager_id' => null,
        'location_id' => null,
        'installed_at' => '',
        'manufacturer' => '',
        'spec' => '',
        'parent_id' => null,
        'asset_type_id' => null,
    ];

    // Detail modal state
    public ?int $selectedAssetId = null;

    public $selectedAsset = null; // ?AssetGuardAsset

    public string $activeTab = 'inspections'; // inspections|items

    public bool $preUseRequired = false;

    // Incident report modal state
    public bool $showIncidentModal = false;

    public ?int $incidentEditingId = null;

    // Incident form state
    public array $incidentForm = [
        'occurred_at' => '',
        'assignee_id' => null,
        'event' => '',
        'actions' => '',
        'status' => 'Waiting',
    ];

    // Incident attachments
    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $incidentFiles = [];

    // Asset photos upload (detail modal)
    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $assetImages = [];

    protected function rulesForm(): array
    {
        $uniqueCode = 'unique:asset_guard_assets,code';
        if ($this->editingId) {
            $uniqueCode .= ','.$this->editingId;
        }

        return [
            'form.code' => ['required', 'string', 'max:100', $uniqueCode],
            'form.name' => ['required', 'string', 'max:255'],
            'form.status' => ['required', 'in:Active,Inactive,UnderMaintenance,Retired'],
            'form.serial_no' => ['nullable', 'string', 'max:255'],
            'form.fixed_asset_no' => ['nullable', 'string', 'max:255'],
            'form.manager_id' => ['nullable', 'integer'],
            'form.location_id' => ['nullable', 'integer', 'exists:asset_guard_locations,id'],
            'form.installed_at' => ['nullable', 'date'],
            'form.manufacturer' => ['nullable', 'string', 'max:255'],
            'form.spec' => ['nullable', 'string'],
            'form.parent_id' => ['nullable', 'integer'],
            'form.asset_type_id' => ['nullable', 'integer', 'exists:asset_guard_asset_types,id'],
        ];
    }

    protected function rulesAssetImages(): array
    {
        return [
            'assetImages' => ['nullable', 'array', 'max:10'],
            'assetImages.*' => ['image', 'max:20480', 'mimetypes:image/jpeg,image/png'],
        ];
    }

    public function getUserOptionsProperty(): Collection
    {
        return \App\Models\User::query()->orderBy('name')->get(['id', 'name']);
    }

    public function getAssetsProperty()
    {
        return AssetGuardAsset::query()
            // Global quick search across key columns
            ->when($this->search !== '', fn ($q) => $q->where(function ($qq) {
                $term = "%{$this->search}%";
                $qq->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhereHas('location', fn ($lq) => $lq->where('name', 'like', $term))
                    ->orWhere('serial_no', 'like', $term)
                    ->orWhere('fixed_asset_no', 'like', $term);
            }))
            // Individual field filters (AND logic)
            ->when($this->searchCode !== '', fn ($q) => $q->where('code', "$this->searchCode"))
            ->when($this->searchName !== '', fn ($q) => $q->where('name', 'like', "%{$this->searchName}%"))
            ->when($this->searchLocation !== '', fn ($q) => $q->where('location_id', $this->searchLocation))
            ->when($this->searchSerial !== '', fn ($q) => $q->where('serial_no', 'like', "%{$this->searchSerial}%"))
            ->when($this->searchFixed !== '', fn ($q) => $q->where('fixed_asset_no', 'like', "%{$this->searchFixed}%"))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->assetTypeId, fn ($q) => $q->where('asset_type_id', $this->assetTypeId))
            ->with(['children' => fn ($q) => $q->orderBy('name'), 'location', 'assetType:id,name'])
            ->latest('id')
            ->limit(100)
            ->get();
    }

    public function resetForm(): void
    {
        $this->form = [
            'code' => '', 'name' => '', 'status' => 'Active', 'serial_no' => '',
            'fixed_asset_no' => '', 'manager_id' => null, 'location_id' => null,
            'installed_at' => '', 'manufacturer' => '', 'spec' => '',
            'parent_id' => null, 'asset_type_id' => null,
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showCreate = true;
    }

    public function openEdit(int $id): void
    {
        $asset = AssetGuardAsset::query()->findOrFail($id);
        $this->editingId = $id;
        $this->form = Arr::only($asset->toArray(), array_keys($this->form));
        $this->showEdit = true;
    }

    public function save(): void
    {
        $data = $this->validate($this->rulesForm());
        AssetGuardAsset::query()->create($data['form']);
        $this->showCreate = false;
        $this->dispatch('saved');
    }

    public function update(): void
    {
        if (! $this->editingId) {
            return;
        }
        $data = $this->validate($this->rulesForm());
        $asset = AssetGuardAsset::query()->findOrFail($this->editingId);
        $asset->update($data['form']);
        $this->showEdit = false;
        $this->dispatch('updated');
    }

    public function openRetireParent(int $parentId): void
    {
        $this->retireParentId = $parentId;
        $this->retireChildrenStrategy = 'cascade';
        $this->showRetireParent = true;
    }

    public function retireParentCommit(): void
    {
        if (! $this->retireParentId) {
            return;
        }
        $parent = AssetGuardAsset::query()->with('children')->findOrFail($this->retireParentId);
        $parent->update(['status' => 'Retired']);

        match ($this->retireChildrenStrategy) {
            'cascade' => $parent->children()->update(['status' => 'Retired']),
            'detach' => $parent->children()->update(['parent_id' => null]),
            'keep' => null,
            default => null,
        };

        $this->showRetireParent = false;
        $this->dispatch('retired');
    }

    // ==== Detail modal and inspection items ====
    public bool $showDetail = false;

    public function openDetail(int $assetId): void
    {
        $this->selectedAssetId = $assetId;
        $this->loadSelectedAsset(true);
        $this->activeTab = 'inspections';

        // Determine whether pre-use inspection is required for today
        $this->preUseRequired = (new PreUseInspectionGate(assetId: $assetId))->isInspectionRequired();

        $this->showDetail = true;
    }

    public function closeDetail(): void
    {
        $this->showDetail = false;
        $this->selectedAssetId = null;
        $this->selectedAsset = null;
        $this->activeTab = 'details';
    }

    public function switchTab(string $tab): void
    {
        if (! in_array($tab, ['inspections', 'items', 'incidents'], true)) {
            return;
        }
        $this->activeTab = $tab;
        $this->loadSelectedAsset(true);
    }

    protected function loadSelectedAsset(bool $relations = false): void
    {
        if ($this->selectedAssetId === null) {
            $this->selectedAsset = null;

            return;
        }
        $query = AssetGuardAsset::query();
        if ($relations) {
            $query->with([
                'inspections.results',
                'inspections.performer',
                'incidents.assignee',
                'media',
            ]);
        }
        $this->selectedAsset = $query->find($this->selectedAssetId);
    }

    /**
     * Map of assetId => bool (whether pre-use inspection is required today)
     */
    public function getPreUseRequiredForListProperty(): array
    {
        $map = [];
        foreach ($this->assets as $asset) {
            $map[$asset->id] = (new PreUseInspectionGate(assetId: $asset->id))->isInspectionRequired();
        }

        return $map;
    }

    /**
     * Map of assetId => int (number of due pending occurrences as of now)
     */
    public function getDueOccurrencesCountForListProperty(): array
    {
        $assetIds = collect($this->assets)->pluck('id')->all();
        if (empty($assetIds)) {
            return [];
        }

        return AssetGuardMaintenancePlan::query()
            ->selectRaw('asset_id, COUNT(*) as due_count')
            ->whereIn('asset_id', $assetIds)
            ->whereDate('scheduled_at', '<=', Carbon::now())
            ->where('status', 'Scheduled')
            ->groupBy('asset_id')
            ->pluck('due_count', 'asset_id')
            ->toArray();
    }

    #[On('pre-use-inspection-finished')]
    #[On('refresh')]
    public function onPreUseFinished(): void
    {
        if ($this->selectedAssetId) {
            $this->loadSelectedAsset(true);
        }
    }

    public function toggleOperatingStatus(): void
    {
        if (! $this->selectedAssetId) {
            return;
        }

        $asset = AssetGuardAsset::findOrFail($this->selectedAssetId);
        $service = app(OperatingStatusService::class);

        $currentStatusForDay = $service->getStatusForDate($asset, now());
        $newStatus = $currentStatusForDay === 'running' ? 'stopped' : 'running';

        $service->setStatusForDay($asset, now(), $newStatus);

        $this->loadSelectedAsset(true);
        // 点検必須判定も再計算
        $this->preUseRequired = (new PreUseInspectionGate(assetId: $this->selectedAssetId))->isInspectionRequired();
        $this->dispatch('refresh');
    }

    public function getLocationOptionsProperty()
    {
        return AssetGuardLocation::query()->orderBy('name')->get(['id', 'name']);
    }

    public function getTypeOptionsProperty()
    {
        return AssetGuardAssetType::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
    }

    public function uploadAssetImages(): void
    {
        if (! $this->selectedAssetId) {
            return;
        }
        $this->validate($this->rulesAssetImages());
        $asset = AssetGuardAsset::query()->findOrFail($this->selectedAssetId);
        foreach ($this->assetImages as $file) {
            try {
                $asset
                    ->addMedia($file->getRealPath())
                    ->usingFileName($file->getClientOriginalName())
                    ->toMediaCollection('photos');
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $this->assetImages = [];
        $this->loadSelectedAsset(true);
        $this->dispatch('saved');
    }

    public function deleteAssetImage(int $mediaId): void
    {
        if (! $this->selectedAssetId) {
            return;
        }
        $asset = AssetGuardAsset::query()->findOrFail($this->selectedAssetId);
        $media = $asset->media->firstWhere('id', $mediaId);
        if ($media) {
            $media->delete();
        }
        $this->loadSelectedAsset(true);
    }

    public function temporaryURL($id)
    {
        $url = URL::temporarySignedRoute(
            config('asset-guard.routes.prefix').'.media.show.signed',
            now()->addMinutes(240), // 240分間有効
            ['media' => $id]
        );

        return $url;
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.assets.index');
    }
}
