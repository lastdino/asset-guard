<?php

namespace Lastdino\AssetGuard;

use GuzzleHttp\Promise\Create;
use Illuminate\Support\ServiceProvider;
use Lastdino\AssetGuard\Livewire\AssetGuard\Assets\Index;
use Lastdino\AssetGuard\Livewire\AssetGuard\Inspections\ChecklistHistoryList;
use Lastdino\AssetGuard\Livewire\AssetGuard\Inspections\Index as InspectionsIndex;
use Lastdino\AssetGuard\Livewire\AssetGuard\Inspections\ChecklistPanel;
use Lastdino\AssetGuard\Livewire\AssetGuard\Inspections\ChecklistItemsPanel;
use Lastdino\AssetGuard\Livewire\AssetGuard\Inspections\ChecklistHistoryPanel;
use Lastdino\AssetGuard\Livewire\AssetGuard\MaintenancePlans\Index as MaintenancePlansIndex;
use Lastdino\AssetGuard\Livewire\AssetGuard\Incidents\IncidentPanel;

use Livewire\Livewire;

class AssetGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/asset-guard.php',
            'asset-guard'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/asset-guard.php' => config_path('asset-guard.php'),
        ],'asset-guard-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/asset-guard'),
        ], 'asset-guard-views');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/asset-guard'),
        ], 'asset-guard-lang');

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'asset-guard');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'asset-guard');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadLivewireComponents();
    }

    // custom methods for livewire components
    protected function loadLivewireComponents(): void
    {
        Livewire::component('asset-guard.assets.index', Index::class);
        Livewire::component('asset-guard.inspections.index', InspectionsIndex::class);
        Livewire::component('asset-guard.inspections.checklist-panel', ChecklistPanel::class);
        Livewire::component('asset-guard.inspections.checklist-items-panel', ChecklistItemsPanel::class);
        Livewire::component('asset-guard.inspections.checklist-history-panel', ChecklistHistoryPanel::class);
        Livewire::component('asset-guard.inspections.checklist-history-list', ChecklistHistoryList::class);
        Livewire::component('asset-guard.inspections.performer', \Lastdino\AssetGuard\Livewire\AssetGuard\Inspections\Performer::class);
        Livewire::component('asset-guard.inspections.batch-performer', \Lastdino\AssetGuard\Livewire\AssetGuard\Inspections\BatchPerformer::class);
        Livewire::component('asset-guard.inspections.pre-use-performer',\Lastdino\AssetGuard\Livewire\AssetGuard\Inspections\PreUsePerformer::class);
        Livewire::component('asset-guard.inspections.show', \Lastdino\AssetGuard\Livewire\AssetGuard\Inspections\Show::class);
        Livewire::component('asset-guard.inspections.checklist-items-editor', \Lastdino\AssetGuard\Livewire\AssetGuard\Inspections\ChecklistItemsEditor::class);
        Livewire::component('asset-guard.maintenance-plans.index', MaintenancePlansIndex::class);

        Livewire::component('asset-guard.incidents.incident-panel', IncidentPanel::class);
        Livewire::component('asset-guard.incidents.index',\Lastdino\AssetGuard\Livewire\AssetGuard\Incidents\Index::class);
        // Dashboard components
        Livewire::component('asset-guard.dashboard.index', \Lastdino\AssetGuard\Livewire\AssetGuard\Dashboard\Index::class);
        Livewire::component('asset-guard.dashboard.kpi-cards', \Lastdino\AssetGuard\Livewire\AssetGuard\Dashboard\KpiCards::class);
        Livewire::component('asset-guard.dashboard.overdue-inspections', \Lastdino\AssetGuard\Livewire\AssetGuard\Dashboard\OverdueInspections::class);
        Livewire::component('asset-guard.dashboard.incidents-table', \Lastdino\AssetGuard\Livewire\AssetGuard\Dashboard\IncidentsTable::class);
        Livewire::component('asset-guard.locations.index', \Lastdino\AssetGuard\Livewire\AssetGuard\Locations\Index::class);
        Livewire::component('asset-guard.asset-types.index', \Lastdino\AssetGuard\Livewire\AssetGuard\AssetTypes\Index::class);

        Livewire::component('asset-guard.asset-types.checklist-manager', \Lastdino\AssetGuard\Livewire\AssetGuard\AssetTypes\ChecklistManager::class);
    }
}
