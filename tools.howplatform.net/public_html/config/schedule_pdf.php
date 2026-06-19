<?php
return [
  'pdf_path' => storage_path('app/templates/groupings/groups-template.pdf'),
      'candidates' => [
        'storage' => 'app/templates/groupings/groups-template.pdf',
        'base'    => 'app/templates/groupings/groups-template.pdf',
        'public'  => 'templates/groupings/groups-template.pdf',
    ],
  'page' => 1,
  'layout' => [
    'margin_left' => 14, 'margin_right' => 14,
    'margin_top' => 40,  'margin_bottom' => 14,
    'title_size' => 16, 'head_size' => 12.5, 'cell_size' => 12,
    'row_h' => 9, 'title_gap' => 6,
  ],
  'title' => 'جدول الطالب',
];
