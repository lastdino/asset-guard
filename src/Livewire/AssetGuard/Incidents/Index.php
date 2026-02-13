<?php

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Incidents;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lastdino\AssetGuard\Models\AssetGuardIncident as Incident;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Index extends Component
{
    use WithFileUploads, WithPagination;

    public int $perPage = 10;

    public string $search = '';

    public ?string $status = null;    // Waiting, InProgress, Completed

    public ?string $severity = null;  // Low, Medium, High, Critical

    public ?string $assetName = null; // filter by asset name

    public ?string $assetCode = null; // filter by asset code

    public string $sortField = 'occurred_at';

    public string $sortDir = 'desc';

    public bool $showViewModal = false;

    public bool $showEditModal = false;

    public ?int $selectedId = null;

    public array $form = [
        'occurred_at' => '',
        'assignee_id' => null,
        'event' => '',
        'actions' => '',
        'status' => 'Waiting',
        'severity' => 'Low',
    ];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $files = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSeverity(): void
    {
        $this->resetPage();
    }

    public function updatingAssetName(): void
    {
        $this->resetPage();
    }

    public function updatingAssetCode(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'asc';
        }
        $this->resetPage();
    }

    #[Computed]
    public function incidents(): LengthAwarePaginator
    {
        return Incident::query()
            ->with(['asset', 'assignee'])
            ->when($this->search !== '', function ($q) {
                $q->where(function ($qq) {
                    $qq->where('event', 'like', "%{$this->search}%")
                        ->orWhere('assignee_name', 'like', "%{$this->search}%")
                        ->orWhere('id', $this->search);
                });
            })
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->severity, fn ($q) => $q->where('severity', $this->severity))
            ->when($this->assetName !== null && $this->assetName !== '', function ($q) {
                $q->whereHas('asset', function ($qa) {
                    $qa->where('name', 'like', "%{$this->assetName}%");
                });
            })
            ->when($this->assetCode !== null && $this->assetCode !== '', function ($q) {
                $q->whereHas('asset', function ($qa) {
                    $qa->where('code', 'like', "%{$this->assetCode}%");
                });
            })
            ->orderBy($this->sortField, $this->sortDir)
            ->paginate($this->perPage)
            ->withQueryString();
    }

    public function openView(int $id): void
    {
        $this->selectedId = $id;
        $this->showViewModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->selectedId = $id;
        $incident = Incident::query()->findOrFail($id);
        $this->form = [
            'occurred_at' => optional($incident->occurred_at)->format('Y-m-d\TH:i'),
            'assignee_id' => $incident->assignee_id,
            'event' => (string) $incident->event,
            'actions' => (string) $incident->actions,
            'status' => (string) $incident->status,
            'severity' => (string) $incident->severity,
        ];
        $this->files = [];
        $this->showEditModal = true;
    }

    public function save(): void
    {
        if (! $this->selectedId) {
            return;
        }
        $incident = Incident::query()->findOrFail($this->selectedId);

        $validated = validator($this->form, [
            'occurred_at' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'event' => ['required', 'string', 'max:2000'],
            'actions' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:Waiting,InProgress,Completed'],
            'severity' => ['required', 'in:Low,Medium,High,Critical'],
        ])->validate();

        $incident->fill([
            'occurred_at' => $validated['occurred_at'] ?? null,
            'assignee_id' => $validated['assignee_id'] ?? null,
            'event' => $validated['event'],
            'actions' => $validated['actions'] ?? null,
            'status' => $validated['status'],
            'severity' => $validated['severity'],
        ])->save();

        // Validate and save attachments if provided
        if (! empty($this->files)) {
            validator(['files' => $this->files], [
                'files' => ['array', 'max:10'],
                'files.*' => ['file', 'max:20480', 'mimetypes:image/jpeg,image/png,application/pdf,text/plain,application/zip'],
            ])->validate();

            foreach ($this->files as $file) {
                try {
                    $incident->addMedia($file->getRealPath())
                        ->usingFileName($file->getClientOriginalName())
                        ->toMediaCollection('attachments');
                } catch (\Throwable $e) {
                    // Media library might be unavailable in some deployments; ignore gracefully
                }
            }

            $this->files = [];
        }

        $this->showEditModal = false;
    }

    public function deleteAttachment(int $mediaId): void
    {
        try {
            $media = Media::query()->findOrFail($mediaId);
            $model = $media->model;

            if (! ($model instanceof Incident)) {
                return;
            }

            if ($this->selectedId === null || $model->id !== $this->selectedId) {
                return;
            }

            $media->delete();
            $this->dispatch('incident-attachment-deleted');
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.incidents.index');
    }
}
