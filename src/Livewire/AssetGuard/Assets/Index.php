<?php

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Assets;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Lastdino\AssetGuard\Models\AssetGuardAsset;
use Lastdino\AssetGuard\Models\AssetGuardAssetType;
use Lastdino\AssetGuard\Models\AssetGuardInspectionChecklist;
use Lastdino\AssetGuard\Models\AssetGuardInspectionChecklistItem;
use Lastdino\AssetGuard\Models\AssetGuardLocation;
use Lastdino\AssetGuard\Models\AssetGuardIncident;
use Lastdino\AssetGuard\Models\AssetGuardMaintenancePlan;
use Lastdino\AssetGuard\Models\AssetGuardMaintenanceOccurrence as Occurrence;
use Lastdino\AssetGuard\Models\AssetGuardInspection;
use Illuminate\Support\Carbon;
use Lastdino\AssetGuard\Services\PreUseInspectionGate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;

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

    // Pre-use checklist selector modal state
    public bool $showPreUseSelector = false;
    public ?int $selectorAssetId = null;
    /** @var array<int, array{id:int, name:string}> */
    public array $selectorOptions = [];

    protected function rulesForm(): array
    {
        $uniqueCode = 'unique:asset_guard_assets,code';
        if ($this->editingId) {
            $uniqueCode .= ',' . $this->editingId;
        }

        return [
            'form.code' => ['required','string','max:100', $uniqueCode],
            'form.name' => ['required','string','max:255'],
            'form.status' => ['required','in:Active,Inactive,UnderMaintenance,Retired'],
            'form.serial_no' => ['nullable','string','max:255'],
            'form.fixed_asset_no' => ['nullable','string','max:255'],
            'form.manager_id' => ['nullable','integer'],
            'form.location_id' => ['nullable','integer','exists:asset_guard_locations,id'],
            'form.installed_at' => ['nullable','date'],
            'form.manufacturer' => ['nullable','string','max:255'],
            'form.spec' => ['nullable','string'],
            'form.parent_id' => ['nullable','integer'],
            'form.asset_type_id' => ['nullable','integer','exists:asset_guard_asset_types,id'],
        ];
    }



    protected function rulesIncident(): array
    {
        return [
            'incidentForm.occurred_at' => ['required','date'],
            'incidentForm.assignee_id' => ['nullable','integer','exists:users,id'],
            'incidentForm.event' => ['required','string','max:2000'],
            'incidentForm.actions' => ['nullable','string','max:5000','required_if:incidentForm.status,Completed'],
            'incidentForm.status' => ['required','in:Waiting,InProgress,Completed'],
            'incidentFiles' => ['nullable','array','max:10'],
            'incidentFiles.*' => ['file','max:20480','mimetypes:image/jpeg,image/png,application/pdf,text/plain,application/zip'],
        ];
    }

    protected function rulesAssetImages(): array
    {
        return [
            'assetImages' => ['nullable','array','max:10'],
            'assetImages.*' => ['image','max:20480','mimetypes:image/jpeg,image/png'],
        ];
    }

    public function getUserOptionsProperty(): Collection
    {
        return \App\Models\User::query()->orderBy('name')->get(['id','name']);
    }

    public function getAssetsProperty()
    {
        return AssetGuardAsset::query()
            // Global quick search across key columns
            ->when($this->search !== '', fn($q) => $q->where(function ($qq) {
                $term = "%{$this->search}%";
                $qq->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhereHas('location', fn($lq) => $lq->where('name', 'like', $term))
                    ->orWhere('serial_no', 'like', $term)
                    ->orWhere('fixed_asset_no', 'like', $term);
            }))
            // Individual field filters (AND logic)
            ->when($this->searchCode !== '', fn($q) => $q->where('code', "$this->searchCode"))
            ->when($this->searchName !== '', fn($q) => $q->where('name', 'like', "%{$this->searchName}%"))
            ->when($this->searchLocation !== '', fn($q) => $q->where('location_id', $this->searchLocation))
            ->when($this->searchSerial !== '', fn($q) => $q->where('serial_no', 'like', "%{$this->searchSerial}%"))
            ->when($this->searchFixed !== '', fn($q) => $q->where('fixed_asset_no', 'like', "%{$this->searchFixed}%"))
            ->when($this->status !== '', fn($q) => $q->where('status', $this->status))
            ->when($this->assetTypeId, fn($q) => $q->where('asset_type_id', $this->assetTypeId))
            ->with(['children' => fn($q) => $q->orderBy('name'), 'location', 'assetType:id,name'])
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
        if (!$this->editingId) {
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
        if (!$this->retireParentId) {
            return;
        }
        $parent = AssetGuardAsset::query()->with('children')->findOrFail($this->retireParentId);
        $parent->update(['status' => 'Retired']);

        match ($this->retireChildrenStrategy) {
            'cascade' => $parent->children()->update(['status' => 'Retired']),
            'detach'  => $parent->children()->update(['parent_id' => null]),
            'keep'    => null,
            default   => null,
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

    public function startPreUseInspection(int $assetId): void
    {

        $gate = new PreUseInspectionGate(assetId: $assetId);
        if (! $gate->isInspectionRequired()) {
            $this->dispatch('notify', type: 'info', message: __('asset-guard::inspections.pre_use_not_required_today'));
            if ($this->selectedAssetId === $assetId) {
                $this->preUseRequired = false;
            }
            return;
        }

        $assetTypeId = \Lastdino\AssetGuard\Models\AssetGuardAsset::query()->whereKey($assetId)->value('asset_type_id');

        $plans = AssetGuardMaintenancePlan::query()
            ->where('asset_id', $assetId)
            ->where(function ($q) { $q->where('trigger_type', 'per_use')->orWhereNull('trigger_type'); })
            ->where('status', 'Scheduled')
            ->whereHas('checklist', function ($q) use ($assetTypeId) {
                $q->where('require_before_activation', true)
                  ->where('active', true)
                  ->where(function ($q) use ($assetTypeId) {
                      $q->where(function ($q) use ($assetTypeId) {
                          $q->where('applies_to', 'asset_type')
                            ->when($assetTypeId, fn($q) => $q->where('asset_type_id', $assetTypeId));
                      })->orWhere(function ($q) {
                          $q->where('applies_to', 'asset');
                      });
                  });
            })
            ->with('checklist:id,name')
            ->get();

        if ($plans->isEmpty()) {
            $this->dispatch('notify', type: 'error', message: __('asset-guard::inspections.no_pre_use_plan'));
            return;
        }

        // Exclude checklists already completed today
        $tz = config('app.timezone');
        $today = Carbon::now($tz)->toDateString();

        $doneTodayChecklistIds = AssetGuardInspection::query()
            ->where('asset_id', $assetId)
            ->whereIn('checklist_id', $plans->pluck('checklist_id'))
            ->where('status', 'Completed')
            ->whereDate('performed_at', $today)
            ->pluck('checklist_id')
            ->unique()
            ->all();

        $pendingPlans = $plans->reject(fn($p) => in_array($p->checklist_id, $doneTodayChecklistIds, true))->values();

        if ($pendingPlans->isEmpty()) {
            $this->dispatch('notify', type: 'info', message: __('asset-guard::inspections.pre_use_not_required_today'));
            if ($this->selectedAssetId === $assetId) {
                $this->preUseRequired = false;
            }
            return;
        }

        if ($pendingPlans->count() === 1) {
            $plan = $pendingPlans->first();
            $this->dispatch('open-pre-use-performer', assetId: $assetId, checklistId: $plan->checklist_id);
            return;
        }

        $options = $pendingPlans->map(fn($p) => ['id' => $p->checklist_id, 'name' => $p->checklist->name])->values();
        $this->dispatch('open-pre-use-selector', assetId: $assetId, checklists: $options);
    }

    public function switchTab(string $tab): void
    {
        if (! in_array($tab, ['inspections','items','incidents'], true)) {
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

        return Occurrence::query()
            ->selectRaw('asset_id, COUNT(*) as due_count')
            ->whereIn('asset_id', $assetIds)
            ->whereDate('planned_at', '<=', Carbon::now())
            ->whereIn('status', ['Scheduled','Overdue'])
            ->groupBy('asset_id')
            ->pluck('due_count', 'asset_id')
            ->toArray();
    }

    #[On('pre-use-inspection-finished')]
    public function onPreUseFinished(): void
    {
        if ($this->selectedAssetId) {
            $this->preUseRequired = false;
            $this->loadSelectedAsset(true);
        }
    }

    #[On('open-pre-use-selector')]
    public function onOpenPreUseSelector(int $assetId, array $checklists): void
    {
        $this->selectorAssetId = $assetId;
        $this->selectorOptions = $checklists;
        $this->showPreUseSelector = true;
    }

    public function getLocationOptionsProperty()
    {
        return AssetGuardLocation::query()->orderBy('name')->get(['id','name']);
    }

    public function getTypeOptionsProperty()
    {
        return AssetGuardAssetType::query()->orderBy('sort_order')->orderBy('name')->get(['id','name']);
    }

    public function openIncident(): void
    {
        $this->incidentEditingId = null;
        $this->incidentForm = [
            'occurred_at' => now()->format('Y-m-d\TH:i'),
            'assignee_id' => null,
            'event' => '',
            'actions' => '',
            'status' => 'Waiting',
        ];
        $this->incidentFiles = [];
        $this->showIncidentModal = true;
    }

    public function openIncidentEdit(int $incidentId): void
    {
        if (! $this->selectedAssetId) {
            return;
        }

        $incident = AssetGuardIncident::query()
            ->where('asset_id', $this->selectedAssetId)
            ->findOrFail($incidentId);

        $this->incidentEditingId = (int) $incident->id;
        $this->incidentForm = [
            'occurred_at' => optional($incident->occurred_at)->format('Y-m-d\\TH:i'),
            'assignee_id' => $incident->assignee_id,
            'event' => $incident->event,
            'actions' => $incident->actions,
            'status' => $incident->status,
        ];
        $this->incidentFiles = [];
        $this->showIncidentModal = true;
    }

    public function saveIncident(): void
    {
        if (! $this->selectedAssetId) {
            return;
        }
        $validated = $this->validate($this->rulesIncident());
        $data = $validated['incidentForm'];

        if ($this->incidentEditingId) {
            // Update existing incident
            $incident = AssetGuardIncident::query()
                ->where('asset_id', $this->selectedAssetId)
                ->findOrFail($this->incidentEditingId);

            // completed_at handling: set when moving to Completed (if not already), clear otherwise
            $completedAt = $data['status'] === 'Completed'
                ? ($incident->completed_at ?? now())
                : null;

            $incident->update([
                ...$data,
                'completed_at' => $completedAt,
            ]);
        } else {
            // Create new incident
            $payload = [
                ...$data,
                'asset_id' => $this->selectedAssetId,
                'completed_at' => $data['status'] === 'Completed' ? now() : null,
            ];

            $incident = AssetGuardIncident::query()->create($payload);
        }

        // Attach newly uploaded files (additive)
        foreach ($this->incidentFiles as $file) {
            try {
                $incident
                    ->addMedia($file->getRealPath())
                    ->usingFileName($file->getClientOriginalName())
                    ->toMediaCollection('attachments');
            } catch (\Throwable $e) {
                // Ignore errors if media library is not installed
            }
        }

        $this->loadSelectedAsset(true);
        $this->showIncidentModal = false;
        $this->incidentEditingId = null;
        $this->dispatch('saved');
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

    public function temporaryURL($id){
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
