<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Lastdino\AssetGuard\Livewire\AssetGuard\Assets\Index;
use Lastdino\AssetGuard\Livewire\AssetGuard\Locations\Index as LocationsIndex;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

Route::middleware(config('asset-guard.routes.middleware'))
    ->prefix(config('asset-guard.routes.prefix'))
    ->name(config('asset-guard.routes.prefix'). '.')
    ->group(function () {

        // Assets index (Livewire class component)
        Route::get('/assets', Index::class)->name('assets.index');

        // Locations index (Livewire class component)
        Route::get('/locations', LocationsIndex::class)->name('locations.index');

        // Dashboard (Livewire class component)
        Route::get('/dashboard', \Lastdino\AssetGuard\Livewire\AssetGuard\Dashboard\Index::class)->name('dashboard.index');

        // Maintenance Plans (Livewire class component)
        Route::get('/maintenance-plans', \Lastdino\AssetGuard\Livewire\AssetGuard\MaintenancePlans\Index::class)->name('maintenance-plans.index');

        // Signed, auth-protected download for incident attachments
        Route::get('/incidents/download/{media}', function (Request $request, int $media) {
            $mediaItem = Media::query()->findOrFail($media);

            // Ensure the media belongs to an AssetGuardIncident
            $model = $mediaItem->model;
            abort_unless($model instanceof \Lastdino\AssetGuard\Models\AssetGuardIncident, 404);

            // Optional: if there is an auth user, you could authorize here via policy
            // For now, require authentication and a valid signature via middleware below.

            return response()->download($mediaItem->getPath(), $mediaItem->file_name);
        })->middleware(['auth', 'signed'])->name('incidents.download');

        // Signed, auth-protected inline view for checklist item reference photos
        Route::get('/inspections/items/media/{media}', function (Request $request, int $media) {
            $mediaItem = Media::query()->findOrFail($media);
            $model = $mediaItem->model;
            // Ensure the media belongs to a checklist item
            abort_unless($model instanceof \Lastdino\AssetGuard\Models\AssetGuardInspectionChecklistItem, 404);

            return response()->file($mediaItem->getPath(), [
                'Content-Type' => $mediaItem->mime_type,
                'Cache-Control' => 'private, max-age=600',
            ]);
        })->middleware(['auth', 'signed'])->name('inspections.items.media');

        // Signed, auth-protected download for inspection result attachments
        Route::get('/inspections/results/download/{media}', function (Request $request, int $media) {
            $mediaItem = Media::query()->findOrFail($media);
            $model = $mediaItem->model;
            abort_unless($model instanceof \Lastdino\AssetGuard\Models\AssetGuardInspectionItemResult, 404);

            return response()->download($mediaItem->getPath(), $mediaItem->file_name);
        })->middleware(['auth', 'signed'])->name('inspections.results.download');

        Route::get('/media/{media}', function (Request $request, $media) {
            $mediaItem = \Spatie\MediaLibrary\MediaCollections\Models\Media::findOrFail($media);
            $path = $mediaItem->getPath(); // 物理パス
            return response()->file($path);
        })->middleware('signed')->withoutMiddleware(['auth'])->name('media.show.signed');
    });
