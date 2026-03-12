<?php

return [
    'default_theme' => env('UI_THEME', 'atlas'),

    'themes' => [
        'atlas' => [
            'label' => 'Atlas',
            'font_heading' => '"Georgia", "Times New Roman", serif',
            'font_body' => '"Trebuchet MS", "Segoe UI", sans-serif',
            'font_mono' => '"Courier New", monospace',
            'colors' => [
                'bg' => '#efe6d1',
                'bg_alt' => '#f8f3e6',
                'panel' => '#fcfaf4',
                'panel_alt' => '#e9dcc1',
                'line' => '#c2b28f',
                'ink' => '#1f2a22',
                'muted' => '#5f675e',
                'accent' => '#9b4d24',
                'accent_alt' => '#294e4a',
                'accent_soft' => '#e7c8a9',
                'success' => '#3f6d53',
                'warning' => '#b16d1a',
            ],
        ],
        'graphite' => [
            'label' => 'Graphite',
            'font_heading' => '"Palatino Linotype", "Book Antiqua", serif',
            'font_body' => '"Verdana", "Geneva", sans-serif',
            'font_mono' => '"Lucida Console", monospace',
            'colors' => [
                'bg' => '#d7dce1',
                'bg_alt' => '#eef1f4',
                'panel' => '#f8fafb',
                'panel_alt' => '#c6ced6',
                'line' => '#8a95a1',
                'ink' => '#18212b',
                'muted' => '#4a5560',
                'accent' => '#a44f2f',
                'accent_alt' => '#23536b',
                'accent_soft' => '#f0d8ce',
                'success' => '#3d6b62',
                'warning' => '#9c6a21',
            ],
        ],
    ],
];
