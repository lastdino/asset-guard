<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Incidents;

use Livewire\Component;
use Livewire\WithFileUploads;
use Lastdino\AssetGuard\Models\AssetGuardIncident;
use App\Models\User;
use Illuminate\Support\Collection;

class IncidentPanel extends Component
{
    use WithFileUploads;

    public int $assetId;

    public ?int $editingId = null;

    public string $statusFilter = 'All';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $files = [];

    public array $form = [
        'occurred_at' => '',
        'assignee_id' => null,
        'event' => '',
        'actions' => '',
        'status' => 'Waiting',
        'severity' => 'Medium',
    ];

    public bool $showModal = false;

    public function mount(int $assetId): void
    {
        $this->assetId = $assetId;
    }

    protected function rules(): array
    {
        return [
            'form.occurred_at' => ['required','date'],
            'form.assignee_id' => ['nullable','integer','exists:users,id'],
            'form.event' => ['required','string','max:2000'],
            'form.actions' => ['nullable','string','max:5000','required_if:form.status,Completed'],
            'form.status' => ['required','in:Waiting,InProgress,Completed'],
            'form.severity' => ['required','in:Low,Medium,High,Critical'],
            'files' => ['nullable','array','max:10'],
            'files.*' => ['file','max:20480','mimetypes:image/jpeg,image/png,application/pdf,text/plain,application/zip'],
        ];
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->form = [
            'occurred_at' => now()->format('Y-m-d\\TH:i'),
            'assignee_id' => null,
            'event' => '',
            'actions' => '',
            'status' => 'Waiting',
            'severity' => 'Medium',
        ];
        $this->files = [];
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $incident = AssetGuardIncident::query()
            ->where('asset_id', $this->assetId)
            ->findOrFail($id);

        $this->editingId = (int) $incident->id;
        $this->form = [
            'occurred_at' => optional($incident->occurred_at)->format('Y-m-d\\TH:i'),
            'assignee_id' => $incident->assignee_id,
            'event' => $incident->event,
            'actions' => $incident->actions,
            'status' => $incident->status,
            'severity' => $incident->severity ?? 'Medium',
        ];
        $this->files = [];
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();
        $data = $validated['form'];

        if ($this->editingId) {
            $incident = AssetGuardIncident::query()
                ->where('asset_id', $this->assetId)
                ->findOrFail($this->editingId);

            $completedAt = $data['status'] === 'Completed' ? ($incident->completed_at ?? now()) : null;

            $incident->update([...$data, 'completed_at' => $completedAt]);
        } else {
            $incident = AssetGuardIncident::query()->create([
                ...$data,
                'asset_id' => $this->assetId,
                'completed_at' => $data['status'] === 'Completed' ? now() : null,
            ]);
        }

        foreach ($this->files as $file) {
            try {
                $incident->addMedia($file->getRealPath())
                    ->usingFileName($file->getClientOriginalName())
                    ->toMediaCollection('attachments');
            } catch (\Throwable $e) {
                // Media library not installed - ignore
            }
        }

        $this->dispatch('incident-saved');
        $this->showModal = false;
        $this->editingId = null;
    }

    public function getIncidentsProperty()
    {
        $query = AssetGuardIncident::query()
            ->with('assignee')
            ->where('asset_id', $this->assetId);

        if ($this->statusFilter !== 'All') {
            $query->where('status', $this->statusFilter);
        }

        return $query->latest('occurred_at')->get();
    }

    public function deleteAttachment(int $mediaId): void
    {
        try {
            $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->findOrFail($mediaId);

            $model = $media->model;

            if (!($model instanceof AssetGuardIncident)) {
                return;
            }

            if ($model->asset_id !== $this->assetId) {
                return;
            }

            $media->delete();

            $this->dispatch('incident-attachment-deleted');
        } catch (\Throwable $e) {
            // ignore deletion errors
        }
    }

    public function getAssigneeOptionsProperty(): Collection
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.incidents.incident-panel');
    }
}
