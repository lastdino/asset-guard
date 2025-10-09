# Asset Guard

日本語 | English follows below

---

## 概要（日本語）
Asset Guard は、設備資産の台帳管理・点検（定期/使用前）・保全計画・故障（インシデント）記録を Livewire v3 + Flux UI + Tailwind v4 で提供するモジュールです。Laravel 12 のモジュール構成で、設定/ルート/ビュー/言語/マイグレーションをパッケージから提供します。

主な機能:
- 資産（設備）台帳: コード、名称、シリアル、固定資産番号、設置場所、階層（親子）など
- 設置場所管理: ツリー構造（親子）
- 点検: チェックリスト（項目/合否/範囲/参考画像）と実施・履歴・添付
- 使用前点検・一括点検: 現場運用を意識した UI
- 保全計画: 時間/使用トリガ、リードタイム、担当者割り当て、発生スケジュール
- 故障（インシデント）: 発生・対応・完了、添付ダウンロード（署名付き URL）
- ダッシュボード: KPI、期限超過、最近の故障

技術スタック:
- Laravel 12, PHP 8.4
- Livewire v3, Volt（一部）, Flux UI Free
- Tailwind CSS v4
- Spatie Media Library（添付）

---

## インストール（モノレポ内）
このパッケージは `packages/lastdino/asset-guard` に配置されています。ルート `composer.json` に PSR-4 およびパッケージの path リポジトリ設定が済んでいる前提です。

1) Service Provider は自動登録（Laravel 12 auto-discovery）
- `Lastdino\AssetGuard\AssetGuardServiceProvider`

2) ルート/ビュー/翻訳/マイグレーションはパッケージから自動ロード
- 変更したい場合は publish を利用（下記）

3) 依存関係（抜粋）
- livewire/livewire: ^3
- tailwindcss: ^4（ビルド必要）
- spatie/laravel-medialibrary

> フロントに変更を加えた後は `npm run dev` もしくは `npm run build` を実行してください。

---

## Publish（任意）
必要に応じて設定・ビュー・言語を publish できます。

- 設定:
  php artisan vendor:publish --tag=asset-guard-config --no-interaction

- ビュー:
  php artisan vendor:publish --tag=asset-guard-views --no-interaction

- 言語:
  php artisan vendor:publish --tag=asset-guard-lang --no-interaction

---

## マイグレーション
マイグレーションは自動ロードされます。以下で適用してください。

php artisan migrate --no-interaction

テーブル例:
- asset_guard_locations, asset_guard_assets
- asset_guard_inspection_checklists, asset_guard_inspection_checklist_items
- asset_guard_inspections, asset_guard_inspection_item_results, asset_guard_inspection_user
- asset_guard_maintenance_plans, asset_guard_maintenance_occurrences
- asset_guard_incidents

---

## ルートと URL
設定ファイル `config/asset-guard.php` で prefix と middleware を変更できます。既定値:

- prefix: `asset-guard`
- middleware: `['web']`

代表ページ（名前付きルート）:
- ダッシュボード: route('asset-guard.dashboard.index') → /asset-guard/dashboard
- 資産一覧: route('asset-guard.assets.index') → /asset-guard/assets
- 設置場所: route('asset-guard.locations.index') → /asset-guard/locations
- 資産タイプ: route('asset-guard.asset-types.index') → /asset-guard/asset-types
- 保全計画: route('asset-guard.maintenance-plans.index') → /asset-guard/maintenance-plans
- 故障一覧: route('asset-guard.incidents.index') → /asset-guard/incidents

メディア関連（署名付き）:
- 故障添付ダウンロード: route('asset-guard.incidents.download', $mediaId)
- 点検項目参考画像の表示: route('asset-guard.inspections.items.media', $mediaId)
- 点検結果添付ダウンロード: route('asset-guard.inspections.results.download', $mediaId)
- 一般メディア表示（署名付き・非認証）: route('asset-guard.media.show.signed', $mediaId)

> download および items.media/results.download は `auth` + `signed` ミドルウェアで保護されています。

---

## Livewire コンポーネント（抜粋）
ServiceProvider にて登録済みです。Blade から呼び出す場合は下記エイリアスを使用できます。

- 'asset-guard.assets.index' → 資産一覧
- 'asset-guard.locations.index' → 設置場所
- 'asset-guard.asset-types.index' / 'asset-guard.asset-types.checklist-manager'
- 'asset-guard.dashboard.index' 他 KPI/テーブル系
- 'asset-guard.inspections.index' / '...performer' / '...batch-performer' / '...pre-use-performer' / '...show' / '...checklist-items-*'
- 'asset-guard.maintenance-plans.index'
- 'asset-guard.incidents.index' / 'asset-guard.incidents.incident-panel'

---

## 設定
config/asset-guard.php

return [
  'routes' => [
    'prefix' => 'asset-guard',
    'middleware' => ['web'],
    'guards' => ['web'],
  ],
];

- prefix を変更すると全ページの URL と名前付きルート接頭辞が変わります。
- middleware/guards をアプリ方針に合わせて調整してください。

---

## UI と i18n
- Tailwind v4 を使用しています。v3 以前の @tailwind ではなく `@import "tailwindcss";` を使用します。
- Flux UI Free を利用。<flux:button> の variant は `primary|filled|danger|ghost|subtle` のみを使用してください。
- 翻訳は `lang/vendor/asset-guard` に publish 可能です。既定では `ja` をデフォルト、`en` をフォールバックで提供する前提です。

---

## よくある操作
- ダッシュボードへの遷移: /asset-guard/dashboard
- 資産の登録・編集・設置場所の紐付け
- 点検チェックリストの作成 → 実施（使用前/定期）→ 添付の確認
- 保全計画の作成 → 予定（Occurrence）の確認
- 故障記録の登録 → 添付のダウンロード（署名付き）

---

## テスト/開発のヒント
- 変更を加えたら最小限のテストを実行: `php artisan test --filter=AssetGuard`（テストが存在する場合）
- フロント/UI 変更後は `npm run dev` か `npm run build` を実行
- コード整形は `vendor/bin/pint --dirty`

---

## English

### Overview
Asset Guard is a Livewire v3 + Flux UI + Tailwind v4 module for asset registry, inspections (periodic/pre-use), maintenance planning, and incident records. Ships routes, views, translations, and migrations as a Laravel 12 package.

Key features:
- Asset register with parent/child, serial/fixed asset no, location
- Locations (hierarchical)
- Inspections with checklists, item ranges, reference media, results & attachments
- Pre-use and batch inspections
- Maintenance plans (time/usage triggers, lead time, assignee)
- Incidents with signed downloads
- Dashboard (KPIs, overdue, incidents)

### Install (in monorepo)
- Service provider is auto-discovered: `Lastdino\\AssetGuard\\AssetGuardServiceProvider`
- Routes/views/translations/migrations are auto-loaded. Use vendor:publish to customize.
- Run frontend build after UI changes: `npm run dev` or `npm run build`.

### Publish (optional)
- Config: `php artisan vendor:publish --tag=asset-guard-config --no-interaction`
- Views:  `php artisan vendor:publish --tag=asset-guard-views --no-interaction`
- Lang:   `php artisan vendor:publish --tag=asset-guard-lang --no-interaction`

### Migrate
`php artisan migrate --no-interaction`

Tables include assets, locations, checklists, inspections, maintenance plans/occurrences, incidents, and pivots.

### Routes & URLs
Default config:
- prefix: `asset-guard`
- middleware: `web`

Pages:
- Dashboard: `/asset-guard/dashboard`
- Assets: `/asset-guard/assets`
- Locations: `/asset-guard/locations`
- Asset Types: `/asset-guard/asset-types`
- Maintenance Plans: `/asset-guard/maintenance-plans`
- Incidents: `/asset-guard/incidents`

Media (signed):
- Incidents download: `asset-guard.incidents.download`
- Checklist item media: `asset-guard.inspections.items.media`
- Inspection result download: `asset-guard.inspections.results.download`
- Generic signed media: `asset-guard.media.show.signed`

### Livewire components (highlights)
See service provider for full list. Examples:
- `asset-guard.assets.index`, `asset-guard.locations.index`, `asset-guard.asset-types.index`
- `asset-guard.dashboard.*`
- `asset-guard.inspections.*` (performer, batch-performer, pre-use-performer, show, checklist-items-*)
- `asset-guard.maintenance-plans.index`
- `asset-guard.incidents.*`

### UI & i18n
- Tailwind v4 (`@import "tailwindcss";`)
- Flux UI Free button variants: primary, filled, danger, ghost, subtle
- Translations can be published to `lang/vendor/asset-guard`

### Tips
- Run focused tests as needed; run frontend build for UI updates
- Format with Pint: `vendor/bin/pint --dirty`

---

## ライセンス
© Lastdino. All rights reserved.
