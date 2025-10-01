<?php

namespace Lastdino\AssetGuard\Livewire\AssetGuard\Dashboard;

use Livewire\Component;

class Index extends Component
{
    public string $period = 'this_month';

    public function render()
    {
        return view('asset-guard::livewire.asset-guard.dashboard.index');
    }
}
