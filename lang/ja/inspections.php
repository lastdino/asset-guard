<?php

return [
    'scheduled_exists' => '予定されている点検があります',
    'scheduled_hint' => '既存の予定に対して実施するか、別のチェックリストを選択してください。',
    'select_checklist' => 'チェックリストを選択',
    'pre_use' => '使用前',
    'start' => '開始',
    'start_inspection' => '点検を開始',
    'heading' => '点検',
    'planned_for' => '予定日',
    'plan' => '計画',
    'checklist' => 'チェックリスト',
    'quick_complete' => '実施（完了）',
    'perform_detail' => '詳細実施',
    'perform_batch' => '一括実施',
    'no_due_items' => '本日時点で実施対象の項目はありません。',
    'due_date' => '期日',
    'empty_occurrences' => '未実施の点検予定はありません。',
    'scheduled_on' => '予定日: :date',
    'overdue' => '期限超過',

    'perform_modal_title' => '点検の実施',
    'target' => '対象',
    'inspector' => '点検者',
    'co_inspectors' => '共同点検者（任意・複数）',
    'pass' => '合格',
    'fail' => '不合格',
    'measured_value' => '実測値',
    'tolerance' => '許容範囲',
    'result' => '結果',
        'result_note' => '結果のメモ',
    'select_placeholder' => '選択してください...',
    'note_placeholder' => '備考（任意）',
    'attachments_label' => '添付（任意）',
    'remove_attachment' => '除外',
    'save_draft' => '下書き保存',
    'save_and_finalize' => '確定して保存',
    'saving' => '保存中...',

    // Filters
    'filter_by_asset_code_placeholder' => '設備コードで絞り込み',
    'clear_filter' => 'クリア',

    // Pre-use
    'start_pre_use' => '使用前点検を開始',
    'starting' => '開始中...',
    'pre_use_not_required_today' => '本日は既に使用前点検が完了しています。',
    'no_pre_use_plan' => '使用前点検のプランが見つかりません。',
        'select_pre_use_checklist' => '使用前点検のチェックリストを選択',

    // List badge
    'pending_short' => '未実施',
    'pending_count' => '{1} 未実施が 1 件|[2,*] 未実施が :count 件',

    // Detail modal
    'detail' => [
        'title' => '点検詳細',
        'performed_at' => '実施日時',
        'performer' => '担当',
        'items' => '点検項目',
        'unknown_item' => '不明な項目',
        'result' => '結果',
        'note' => '備考',

        'not_found' => '点検が見つかりません。',
        'attachments' => '添付',
        'download' => 'ダウンロード',
    ],
    'upcoming' => '今後の予定',

    // Quick/Inspector setup
    'choose_inspector_title' => '点検者を設定',
    'reset_inspector' => '点検者をリセット',
    'please_set_inspector' => '点検者を設定してください。',
    'please_set_inspector_hint' => 'まず点検者を設定してください。',
];
