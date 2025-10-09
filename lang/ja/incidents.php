<?php

return [
    'index' => [
        'title' => 'インシデント一覧',
        'empty' => 'インシデントはありません。',
    ],
    'columns' => [
        'id' => 'ID',
        'title' => '件名',
        'status' => 'ステータス',
        'severity' => '重要度',
        'occurred_at' => '発生日時',
        'reporter' => '報告者',
        'asset' => '設備名',
        'actions' => '対処',
    ],
    'filters' => [
        'status' => [
            'all' => 'すべてのステータス',
        ],
        'severity' => [
        'all' => 'すべての重要度',
    ],
    'asset_name' => '設備名で検索',
    'asset_code' => '設備コードで検索',
],
    'status' => [
        'waiting' => '受付',
        'in_progress' => '対応中',
        'completed' => '完了',
    ],
    'severity' => [
        'low' => '低',
        'medium' => '中',
        'high' => '高',
        'critical' => '重大',
    ],
    'modal' => [
        'view_title' => 'インシデント詳細',
        'edit_title' => 'インシデントを編集',
    ],
];
