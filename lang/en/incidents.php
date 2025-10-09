<?php

return [
    'index' => [
        'title' => 'Incidents',
        'empty' => 'No incidents found.',
    ],
    'columns' => [
        'id' => 'ID',
        'title' => 'Title',
        'status' => 'Status',
        'severity' => 'Severity',
        'occurred_at' => 'Occurred At',
        'reporter' => 'Reporter',
        'asset' => 'Asset',
        'actions' => 'Actions Taken',
    ],
    'filters' => [
        'status' => [
            'all' => 'All Statuses',
        ],
        'severity' => [
        'all' => 'All Severities',
    ],
    'asset_name' => 'Filter by asset name',
    'asset_code' => 'Filter by asset code',
],
    'status' => [
        'waiting' => 'Waiting',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
    ],
    'severity' => [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ],
    'modal' => [
        'view_title' => 'Incident Details',
        'edit_title' => 'Edit Incident',
    ],
];
