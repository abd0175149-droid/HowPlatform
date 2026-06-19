<?php
return [
    // المسار المباشر للقالب (لاندسكيب)
    'pdf_path' => storage_path('app/templates/groupings/groups-template.pdf'),

    // رقم الصفحة داخل ملف القالب (إن كان متعدد الصفحات)
    'page' => 1,

    // مسارات بديلة إذا لم يوجد المسار الأساسي
    'candidates' => [
        'storage' => 'app/templates/groupings/groups-template.pdf',
        'base'    => 'app/templates/groupings/groups-template.pdf',
        'public'  => 'templates/groupings/groups-template.pdf',
    ],

    // تخطيط الكتابة فوق القالب (مم)
    'layout' => [
        // هوامش الصفحة
        'margin_left'   => 14,   // أكبر كما طلبت
        'margin_right'  => 14,
        'margin_top'    => 45,   // مسافة أكبر ببداية الصفحة
        'margin_bottom' => 14,

        // شبكة بطاقات بنمط masonry (عمودين ثابتين، ارتفاع متغيّر)
        'columns' => 2,
        'gap'     => 7,    // فراغ بين البطاقات

        // الحشوات داخل البطاقة
        'card_pad_x' => 4, // حافة داخلية يمين/يسار
        'card_pad_y' => 3, // حافة داخلية أعلى/أسفل
        'title_gap'  => 2, // فراغ صغير تحت العنوان قبل الأسماء

        // أحجام الخطوط والسطور
        'title_size'   => 15,   // عنوان المجموعة
        'member_size'  => 12,   // أسماء الأعضاء (سيصغر تلقائيًا فقط إذا كانت البطاقة أطول من الصفحة)
        'line_h'       => 6.2,  // ارتفاع سطر الاسم
        'min_member_size' => 8.5, // أصغر حجم يُسمح به تلقائيًا إذا اضطرينا لتصغير
        'bullet'       => '*',
'rtl_columns' => false,
        // إطار البطاقة
        'stroke_gray'  => 210,  // لون الإطار
        'stroke_w'     => 0.2,  // سماكة الإطار
        'radius'       => 2,    // زوايا مستديرة
    ],
];
