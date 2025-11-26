<?php

declare(strict_types=1);

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Inspections;

use Livewire\Attributes\On;
use Livewire\Component;
use Lastdino\AssetGuard\Models\AssetGuardInspection;

class Show extends Component
{
    public bool $open = false;

    public ?AssetGuardInspection $inspection = null;

    #[On('open-inspection-detail')]
    public function open(int $id): void
    {
        $this->inspection = AssetGuardInspection::query()
            ->with(['performer', 'results.item'])
            ->find($id);

        $this->open = $this->inspection !== null;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.inspections.show');
    }
}
