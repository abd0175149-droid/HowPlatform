<?php
return [
    // مسار ملف القوالب الموحّد (ارفعه هنا)
    'pdf_path' => base_path('app/templates/idcard/all-universities.pdf'),

    // خريطة الجامعات -> رقم الصفحة + مواقع الختم لــ Slot1 و Slot2
    // (الإحداثيات بالـ mm من أعلى-يسار، عدّلها لاحقًا لتطابق تصميمك)
    'universities' => [
        // الجامعة الأردنية — الصفحة 1
        'jordan' => [
            'page'  => 1,
            'slots' => [
                // Slot 1
                [
                    //['key'=>'year',  'x'=>95,'y'=>37,'w'=>70,'align'=>'R','size'=>11,'bold'=>true,'color'=>'#111'],
                    ['key'=>'name',  'x'=>51, 'y'=>80,'w'=>120,'align'=>'C','size'=>10,'bold'=>false,'color'=>'#111'],
                    ['key'=>'major', 'x'=>50, 'y'=>87,'w'=>120,'align'=>'C','size'=>10,'bold'=>false,'color'=>'#333'],
                ],
                // Slot 2
               /* [
                    ['key'=>'year',  'x'=>95,'y'=>188,'w'=>80,'align'=>'R','size'=>12,'bold'=>true,'color'=>'#111'],
                    ['key'=>'name',  'x'=>55, 'y'=>198,'w'=>120,'align'=>'C','size'=>16,'bold'=>true,'color'=>'#111'],
                    ['key'=>'major', 'x'=>55, 'y'=>209,'w'=>120,'align'=>'C','size'=>13,'bold'=>false,'color'=>'#333'],
                ],*/
                
            ],
        ],

        // اليرموك — الصفحة 2
        'yarmouk' => [
            'page'  => 2,
            'slots' => [
                // Slot 1
                [
                    ['key'=>'year',  'x'=>95,'y'=>37,'w'=>70,'align'=>'R','size'=>11,'bold'=>true,'color'=>'#111'],
                    ['key'=>'name',  'x'=>44, 'y'=>78,'w'=>120,'align'=>'C','size'=>15,'bold'=>true,'color'=>'#111'],
                    ['key'=>'major', 'x'=>44, 'y'=>86,'w'=>120,'align'=>'C','size'=>13,'bold'=>false,'color'=>'#333'],
                ],
                // Slot 2
                [
                    ['key'=>'year',  'x'=>95,'y'=>188,'w'=>80,'align'=>'R','size'=>12,'bold'=>true,'color'=>'#111'],
                    ['key'=>'name',  'x'=>55, 'y'=>198,'w'=>120,'align'=>'C','size'=>16,'bold'=>true,'color'=>'#111'],
                    ['key'=>'major', 'x'=>55, 'y'=>209,'w'=>120,'align'=>'C','size'=>13,'bold'=>false,'color'=>'#333'],
                ],
            ],
        ],

        // مؤتة — الصفحة 3
        'mutah' => [
            'page'  => 3,
            'slots' => [
                // Slot 1
                [
                    ['key'=>'year',  'x'=>95,'y'=>37,'w'=>70,'align'=>'R','size'=>11,'bold'=>true,'color'=>'#111'],
                    ['key'=>'name',  'x'=>44, 'y'=>78,'w'=>120,'align'=>'C','size'=>15,'bold'=>true,'color'=>'#111'],
                    ['key'=>'major', 'x'=>44, 'y'=>86,'w'=>120,'align'=>'C','size'=>13,'bold'=>false,'color'=>'#333'],
                ],
                // Slot 2
                [
                    ['key'=>'year',  'x'=>95,'y'=>188,'w'=>80,'align'=>'R','size'=>12,'bold'=>true,'color'=>'#111'],
                    ['key'=>'name',  'x'=>55, 'y'=>198,'w'=>120,'align'=>'C','size'=>16,'bold'=>true,'color'=>'#111'],
                    ['key'=>'major', 'x'=>55, 'y'=>209,'w'=>120,'align'=>'C','size'=>13,'bold'=>false,'color'=>'#333'],
                ],
            ],
        ],

        // الهاشمية — الصفحة 4
        'hashemite' => [
            'page'  => 4,
            'slots' => [
                // Slot 1
                [
                    ['key'=>'year',  'x'=>95,'y'=>37,'w'=>70,'align'=>'R','size'=>11,'bold'=>true,'color'=>'#111'],
                    ['key'=>'name',  'x'=>44, 'y'=>78,'w'=>120,'align'=>'C','size'=>15,'bold'=>true,'color'=>'#111'],
                    ['key'=>'major', 'x'=>44, 'y'=>86,'w'=>120,'align'=>'C','size'=>13,'bold'=>false,'color'=>'#333'],
                ],
                // Slot 2
                [
                    ['key'=>'year',  'x'=>95,'y'=>188,'w'=>80,'align'=>'R','size'=>12,'bold'=>true,'color'=>'#111'],
                    ['key'=>'name',  'x'=>55, 'y'=>198,'w'=>120,'align'=>'C','size'=>16,'bold'=>true,'color'=>'#111'],
                    ['key'=>'major', 'x'=>55, 'y'=>209,'w'=>120,'align'=>'C','size'=>13,'bold'=>false,'color'=>'#333'],
                ],
            ],
        ],
    ],
];
