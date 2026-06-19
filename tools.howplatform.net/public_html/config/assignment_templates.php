<?php

return [
    // المسار الافتراضي داخل Storage، مع وجود fallback في الكنترولر
    'pdf_path' => storage_path('app/templates/assignment/all-universities.pdf'),

    // إعداد الخط الافتراضي
    'font' => [
        'regular' => 'Amiri-Regular.ttf',
        'bold'    => 'Amiri-Bold.ttf',
        'size'    => 12.0,
        'color'   => '#111111',
    ],

    'universities' => [
        'jordan' => [
            'page'  => 1,
            'slots' => [
                // الكتلة الأولى (5 أسطر متتالية)
                [
                    // tip: استخدم عرض كبير (w=226mm) ليتاح محاذاة R في RTL على عرض الصفحة
                    // اضبط x,y حسب قالبك. القيم التالية مناسبة كافتراض منطقي.
                    ['key'=>'student_name',     'x'=>48, 'y'=>85,  'w'=>226, 'align'=>'R', 'size'=>12, 'bold'=>true],
                    ['key'=>'student_id',       'x'=>38, 'y'=>94,  'w'=>226, 'align'=>'R'],
                    ['key'=>'specialty',        'x'=>30, 'y'=>102,  'w'=>226, 'align'=>'R'],
                    ['key'=>'course_teacher',   'x'=>44, 'y'=>110,  'w'=>226, 'align'=>'R'],
                    ['key'=>'academic_advisor', 'x'=>49, 'y'=>118,  'w'=>226, 'align'=>'R'],
                ],
                // فراغ سطر واحد (y قفزة بمقدار ~10mm)، ثم المجموعة الثانية
                [
                    ['key'=>'course_name',      'x'=>34, 'y'=>139,  'w'=>226, 'align'=>'R', 'bold'=>true],
                    ['key'=>'task_type',        'x'=>30, 'y'=>148, 'w'=>226, 'align'=>'R'],
                    ['key'=>'date',             'x'=>38, 'y'=>157, 'w'=>226, 'align'=>'R'],
                ],
                // فراغ سطرين (~20mm)، ثم مساحة الإجابة
                [
                    // h=0 يعني اترك TCPDF يتمدّد تلقائيًا؛ مع تفعيل AutoPageBreak سيكمل للصفحة التالية الفارغة تلقائيًا.
                    ['key'=>'answer_html',      'x'=>18, 'y'=>168, 'w'=>200, 'h'=>0,  'align'=>'R', 'size'=>14],
                ],
            ],
        ],

        // تأكّد من نقل نفس الفلسفة (خطوط y والسطر/الفراغ) إلى بقية الجامعات مع تحريك y/x حسب قالب كل صفحة.
        'yarmouk' => [
            'page'  => 2,
            'slots' => [
                [
                    ['key'=>'student_name',     'x'=>24, 'y'=>38,  'w'=>226, 'align'=>'R', 'size'=>12, 'bold'=>true],
                    ['key'=>'student_id',       'x'=>24, 'y'=>48,  'w'=>226, 'align'=>'R'],
                    ['key'=>'specialty',        'x'=>24, 'y'=>58,  'w'=>226, 'align'=>'R'],
                    ['key'=>'course_teacher',   'x'=>24, 'y'=>68,  'w'=>226, 'align'=>'R'],
                    ['key'=>'academic_advisor', 'x'=>24, 'y'=>78,  'w'=>226, 'align'=>'R'],
                ],
                [
                    ['key'=>'course_name',      'x'=>24, 'y'=>92,  'w'=>226, 'align'=>'R', 'bold'=>true],
                    ['key'=>'task_type',        'x'=>24, 'y'=>102, 'w'=>226, 'align'=>'R'],
                    ['key'=>'date',             'x'=>24, 'y'=>112, 'w'=>226, 'align'=>'R'],
                ],
                [
                    ['key'=>'answer_html',      'x'=>24, 'y'=>134, 'w'=>226, 'h'=>0,  'align'=>'R', 'size'=>12],
                ],
            ],
        ],

        'mutah' => [
            'page'  => 3,
            'slots' => [
                [
                    ['key'=>'student_name',     'x'=>24, 'y'=>38,  'w'=>226, 'align'=>'R', 'size'=>12, 'bold'=>true],
                    ['key'=>'student_id',       'x'=>24, 'y'=>48,  'w'=>226, 'align'=>'R'],
                    ['key'=>'specialty',        'x'=>24, 'y'=>58,  'w'=>226, 'align'=>'R'],
                    ['key'=>'course_teacher',   'x'=>24, 'y'=>68,  'w'=>226, 'align'=>'R'],
                    ['key'=>'academic_advisor', 'x'=>24, 'y'=>78,  'w'=>226, 'align'=>'R'],
                ],
                [
                    ['key'=>'course_name',      'x'=>24, 'y'=>92,  'w'=>226, 'align'=>'R', 'bold'=>true],
                    ['key'=>'task_type',        'x'=>24, 'y'=>102, 'w'=>226, 'align'=>'R'],
                    ['key'=>'date',             'x'=>24, 'y'=>112, 'w'=>226, 'align'=>'R'],
                ],
                [
                    ['key'=>'answer_html',      'x'=>24, 'y'=>134, 'w'=>226, 'h'=>0,  'align'=>'R', 'size'=>12],
                ],
            ],
        ],

        'hashemite' => [
            'page'  => 4,
            'slots' => [
                [
                    ['key'=>'student_name',     'x'=>24, 'y'=>38,  'w'=>226, 'align'=>'R', 'size'=>12, 'bold'=>true],
                    ['key'=>'student_id',       'x'=>24, 'y'=>48,  'w'=>226, 'align'=>'R'],
                    ['key'=>'specialty',        'x'=>24, 'y'=>58,  'w'=>226, 'align'=>'R'],
                    ['key'=>'course_teacher',   'x'=>24, 'y'=>68,  'w'=>226, 'align'=>'R'],
                    ['key'=>'academic_advisor', 'x'=>24, 'y'=>78,  'w'=>226, 'align'=>'R'],
                ],
                [
                    ['key'=>'course_name',      'x'=>24, 'y'=>92,  'w'=>226, 'align'=>'R', 'bold'=>true],
                    ['key'=>'task_type',        'x'=>24, 'y'=>102, 'w'=>226, 'align'=>'R'],
                    ['key'=>'date',             'x'=>24, 'y'=>112, 'w'=>226, 'align'=>'R'],
                ],
                [
                    ['key'=>'answer_html',      'x'=>24, 'y'=>134, 'w'=>226, 'h'=>0,  'align'=>'R', 'size'=>12],
                ],
            ],
        ],
    ],
];
