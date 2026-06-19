<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    /** الصفحة الرئيسية للموقع */
    public function indexOLD(): View
    {
        // إعدادات SEO
        $seo = [
            'title'       => 'منصة كيف التعليمية | HOWPlatform',
            'description' => 'منصة عربية تُسخّر التقنية لتطوير التعليم. ابدأ من الدبلوم العالي لإعداد المعلمين، وتعرّف على خدماتنا ومواردنا.',
            'canonical'   => url()->current(),
            'og_image'    => asset('assets/images/Logo_how_w.svg'),
        ];

        // إحصائيات افتراضية (لاحقًا اربطها بقاعدة البيانات)
        $stats = [
            ['label' => 'عدد الزوار',       'target' => 25480, 'duration' => 2600],
            ['label' => 'الملفات المحمّلة', 'target' => 3850,  'duration' => 3000],
            ['label' => 'مرّات التحميل',     'target' => 12850, 'duration' => 3400],
        ];

        // أسئلة شائعة مختصرة (إن رغبت بعرضها لاحقًا)
        $faqs = [
            ['q' => 'ما هي HOWPlatform؟', 'a' => 'منصة تعليمية عربية تدعم المؤسسات والمعلمين والطلبة عبر حلول رقمية ومواد عملية.'],
            ['q' => 'كيف أبدأ مع الدبلوم العالي؟', 'a' => 'انتقل إلى المنصة الفرعية للدبلوم العالي لترى المحتوى والخدمات المرافقة.'],
        ];

        // ملاحظة: لا نستخدم مجلد pages؛ العرض هنا مباشرة home.blade.php
        return view('home', compact('seo', 'stats', 'faqs'));
    }
public function indexXXX(): View
{
    $seo = [
        'title'       => 'منصة كيف التعليمية | HOWPlatform',
        'description' => 'منصة عربية تُسخّر التقنية لتطوير التعليم. ابدأ من الدبلوم العالي لإعداد المعلمين، وتعرّف على خدماتنا ومواردنا.',
        'canonical'   => url()->current(),
        'og_image'    => asset('assets/images/Logo_how_gr.svg'),
    ];

    $statsJson = Cache::store('file')->remember('lib.metrics.overview', 300, function () {
        $base = rtrim(env('LIBRARY_BASE_URL', 'https://library.howplatform.net'), '/');
        return Http::timeout(5)
            ->acceptJson()
            ->withHeaders(['X-Api-Key' => env('LIBRARY_API_KEY')])
            ->get($base.'/api/metrics/overview', ['days' => 30])
            ->json();
    });

    // خرْط الأرقام المطلوبة
    $visitors        = (int)($statsJson['totals']['unique_ips'] ?? 0);       // عدد الزوار
    $filesCount      = (int)($statsJson['totals']['files_count'] ?? 0);      // عدد الملفات
    $downloadsTotal  = (int)($statsJson['totals']['downloads_total'] ?? 0);  // مرّات التحميل

    // نفس البنية التي تتوقعها الواجهة
    $stats = [
        ['label' => 'عدد الزوار (30 يوم)', 'target' => $visitors,       'duration' => 2600],
        ['label' => 'عدد الملفات',         'target' => $filesCount,     'duration' => 3000],
        ['label' => 'مرّات التحميل',       'target' => $downloadsTotal,  'duration' => 3400],
    ];

    return view('home', compact('seo', 'stats'));
}
    private function platformsData(): array
    {
        return [
            [
                'title' => 'منصة الدبلوم العالي',
                'desc'  => 'مواد، مهام، وإرشادات برنامج الدبلوم العالي في التربية.',
                'href'  => 'https://diploma.howplatform.net',
                'icon'  => 'fas fa-graduation-cap',
                'btn'   => 'btn-diploma',
                'cta'   => 'انضم إلى مجتمعنا',
            ],
            [
                'title' => 'المكتبة الرقمية',
                'desc'  => 'مصادر تعليمية منظّمة حسب الصفوف والمباحث والفصول، مع فلاتر ذكية.',
                'href'  => 'https://library.howplatform.net',
                'icon'  => 'fas fa-book-open',
                'btn'   => 'btn-library',
                'cta'   => 'استكشف المكتبة',
            ],
            [
                'title' => 'الأدوات الرقمية',
                'desc'  => 'منصّة موحّدة لأدوات تعليمية وخدمية صغيرة قابلة للتوسّع، تتكامل مع منصّات HOW.',
                'href'  => 'https://tools.howplatform.net',
                'icon'  => 'fas fa-toolbox',
                'btn'   => 'btn-tools',
                'cta'   => 'جرّب الأدوات',
            ],
            [
                'title' => 'منصّات قادمة',
                'desc'  => 'نعمل على إطلاق منصّات متخصصة لخدمة المجتمع التعليمي، قريبًا إن شاء الله.',
                'href'  => '#',
                'icon'  => 'fas fa-rocket',
                'btn'   => 'btn-ghost',
                'cta'   => 'قريبًا',
            ],
        ];
    }

    /** الصفحة الرئيسية */
    public function index(): View
    {
        $seo = [
            'title'       => 'منصة كيف التعليمية | HOWPlatform',
            'description' => 'منصة عربية تُسخّر التقنية لتطوير التعليم. ابدأ من الدبلوم العالي لإعداد المعلمين، وتعرّف على خدماتنا ومواردنا.',
            'canonical'   => url()->current(),
            'og_image'    => asset('assets/images/Logo_how_gr.svg'),
        ];

        $statsJson = Cache::store('file')->remember('lib.metrics.overview', 300, function () {
            $base = rtrim(env('LIBRARY_BASE_URL', 'https://library.howplatform.net'), '/');
            return Http::timeout(5)
                ->acceptJson()
                ->withHeaders(['X-Api-Key' => env('LIBRARY_API_KEY')])
                ->get($base . '/api/metrics/overview', ['days' => 30])
                ->json();
        });

        $visitors       = (int)($statsJson['totals']['unique_ips'] ?? 0);
        $filesCount     = (int)($statsJson['totals']['files_count'] ?? 0);
        $downloadsTotal = (int)($statsJson['totals']['downloads_total'] ?? 0);

        $stats = [
            ['label' => 'عدد الزوار (30 يوم)', 'target' => $visitors,       'duration' => 2600],
            ['label' => 'عدد الملفات',         'target' => $filesCount,     'duration' => 3000],
            ['label' => 'مرّات التحميل',       'target' => $downloadsTotal,  'duration' => 3400],
        ];

        $platforms = $this->platformsData();

        return view('home', compact('seo', 'stats', 'platforms'));
    }

    /** صفحة عرض المنصّات */
    public function platforms()
    {
        $seo = [
            'title'       => 'منصّاتنا | منصة كيف التعليمية',
            'description' => 'تعرّف على المنصّات الفرعية لمنصة كيف التعليمية: الدبلوم العالي والمكتبة الرقمية، والمزيد قريبًا.',
            'url'         => url('/platforms'),
        ];

        $platforms = $this->platformsData();

        return response()->view('platforms', compact('seo', 'platforms'));
    }


    public function about()
    {
        $seo = [
            'title'       => 'حول منصة كيف التعليمية | How Platform',
            'description' => 'نحن مجموعة من الشباب نؤمن بأن التعليم هو سبيل نهضة الشعوب. منصة كيف التعليمية تجمع حلولاً رقمية متكاملة لدعم العملية التعليمية في الأردن والعالم العربي.',
            'url'         => url('/about'),
        ];
        return response()->view('about', compact('seo'));
    }
public function services()
{
    $seo = [
        'title'       => 'خدمات منصة كيف التعليمية | How Platform',
        'description' => 'نقدم خدمات تعليمية رقمية متكاملة في الأردن: محتوى أكاديمي، أدوات تفاعلية، دعم فني، تدريب، واستشارات تربوية.',
        'url'         => url('/services'),
    ];
    return response()->view('services', compact('seo'));
}
public function support()
{
    $seo = [
        'title'       => 'الدعم الفني | منصة كيف التعليمية',
        'description' => 'تواصل مع فريق الدعم الفني لمنصة كيف التعليمية: استفسارات، اقتراحات، أو بلاغات.',
        'url'         => url('/support'),
    ];
    return response()->view('support', compact('seo'));
}
public function contact()
{
    $seo = [
        'title'       => 'التواصل | منصة كيف التعليمية',
        'description' => 'تواصل مع منصة كيف التعليمية عبر واتساب، تيليجرام، فيسبوك، وإنستغرام.',
        'url'         => url('/contact'),
    ];

    // روابط السوشيال كما زوّدتني بها
    $social = [
        'facebook' => 'https://www.facebook.com/people/%D9%85%D9%86%D8%B5%D8%A9-%D9%83%D9%8A%D9%81-%D8%A7%D9%84%D8%AA%D8%B9%D9%84%D9%8A%D9%85%D9%8A%D8%A9-%D8%A7%D9%84%D8%AF%D8%A8%D9%84%D9%88%D9%85-%D8%A7%D9%84%D8%B9%D8%A7%D9%84%D9%8A/61579341362175/',
        'instagram'=> 'https://www.instagram.com/diploma_how?utm_source=ig_web_button_share_sheet&igsh=Z2RyM3V1djF5cnlm',
        'telegram' => 'https://t.me/howplatform_diploma',
        'whatsapp' => 'http://wa.me/962782550901',
    ];

    return response()->view('contact', compact('seo','social'));
}
public function privacy()
{
    $seo = [
        'title'       => 'سياسة الخصوصية | منصة كيف التعليمية',
        'description' => 'تعرف على سياسة الخصوصية الخاصة بمنصة كيف التعليمية، وكيف نحمي بياناتك ونلتزم بأفضل معايير الأمان.',
        'url'         => url('/privacy'),
    ];
    return response()->view('privacy', compact('seo'));
}
public function terms()
{
    $seo = [
        'title'       => 'الشروط والأحكام | منصة كيف التعليمية',
        'description' => 'الشروط والأحكام الخاصة باستخدام منصة كيف التعليمية: حسابات المستخدمين، الملكية الفكرية، المدفوعات، وحدود المسؤولية.',
        'url'         => url('/terms'),
    ];
    return response()->view('terms', compact('seo'));
}

public function faq()
{
    $seo = [
        'title'       => 'الأسئلة الشائعة | منصة كيف التعليمية',
        'description' => 'إجابات موثوقة حول المنصة الرئيسية «كيف التعليمية»: ما هي المنصة وما يميّزها، علاقتها بالمنصات الفرعية (الدبلوم العالي، المكتبة الرقمية)، نوع المحتوى وجودته، سياسات الإعلانات، فرص الشراكة، خطط التطوير، وأفضل قنوات التواصل.',
        'url'         => url('/faq'),
    ];

    // الأسئلة «الخاصة بالمنصة الرئيسية فقط»
    $faq = [
        [
            'q' => 'ما هي منصة كيف التعليمية؟ وما الذي يميّزها؟',
            'a' => 'كيف التعليمية هي المنصّة العربية الرائدة التي تُعيد صياغة مستقبل التعليم بالتقنية. نوفّر منظومة متكاملة تجمع بين المحتوى العملي، الأدوات الرقمية، والخدمات الداعمة للمعلم والطالب والمؤسسة.'
        ],
        [
            'q' => 'كيف ترتبط المنصّة الرئيسية بالمنصّات الفرعية (الدبلوم العالي، المكتبة الرقمية)؟',
            'a' => 'المنصّة الرئيسية هي البوابة التعريفية الجامعة. ننطلق منها إلى منصّات متخصصة مثل <a href="https://diploma.howplatform.net" target="_blank" rel="noopener">منصة الدبلوم العالي</a> و<a href="https://library.howplatform.net" target="_blank" rel="noopener">المكتبة الرقمية</a>، ولكل منصة صفحاتها ووظائفها وتعليماتها الخاصة.'
        ],
        [
            'q' => 'ما نوع المحتوى الذي تُركّز عليه كيف التعليمية؟',
            'a' => 'نركز على المحتوى العملي الداعم للممارسة الصفية والتعلّم الجامعي، بما في ذلك الموارد الرقمية، النماذج الجاهزة، والإرشادات، مع اهتمام خاص برحلة الدبلوم العالي لإعداد المعلمين.'
        ],
        [
            'q' => 'هل تقدّم المنصّة خدمات مدفوعة أو اشتراكات؟',
            'a' => 'قد تتضمن بعض الخدمات المتخصصة اشتراكات ضمن المنصّات الفرعية. تفاصيل كل باقة تُعرض داخل المنصة المعنية. للاطلاع على نظرة عامة، تفضّل بزيارة <a href="'.url('/services').'">صفحة الخدمات</a>.'
        ],
        [
            'q' => 'كيف تختارون الموارد التعليمية وتضمنون جودتها؟',
            'a' => 'نقيّم الموارد وفق معايير داخلية تشمل الملاءمة للمناهج، الدقة العلمية، ووضوح العرض. نُحدّث المحتوى دوريًا ونراعي حقوق النشر والاستخدام العادل.'
        ],
        [
            'q' => 'هل يمكن للمعلمين أو الجهات التربوية التعاون أو الشراكة معكم؟',
            'a' => 'نرحّب بالشراكات التي تخدم المجتمع التعليمي. راسلنا عبر <a href="'.route('contact').'">صفحة التواصل</a> أو <a href="'.route('support').'">الدعم</a> لبحث فرص التعاون.'
        ],
        [
            'q' => 'كيف أبلّغ عن مشكلة في المحتوى أو أقدّم اقتراح تحسين؟',
            'a' => 'يسعدنا استلام ملاحظاتك عبر <a href="'.route('support').'">صفحة الدعم الفني</a>. نراجع الطلبات دوريًا ونعمل على تنفيذها حسب الأولوية.'
        ],
        [
            'q' => 'هل تتضمن المنصّة إعلانات؟ وكيف تُنظَّم؟',
            'a' => 'قد تظهر مساحات إعلانية محدودة لا تخل بتجربة المستخدم ولا تتعارض مع رسالتنا. نحرص على تمييز الإعلان بوضوح والالتزام بسياسة مناسبة للجمهور التربوي.'
        ],
        [
            'q' => 'ما خططكم المستقبلية لتطوير المنصّة الرئيسية؟',
            'a' => 'نواصل التوسع بإطلاق منصّات متخصصة وخدمات جديدة، مع تحسينات مستمرة في الأداء والوصولية وتجربة المستخدم على مختلف الأجهزة.'
        ],
        [
            'q' => 'أين أجد معلومات التسجيل أو استعادة كلمة المرور؟',
            'a' => 'هذه المعلومات تخص المنصّات الفرعية. يُرجى زيارة صفحات المساعدة داخل <a href="https://diploma.howplatform.net" target="_blank" rel="noopener">منصة الدبلوم العالي</a> أو <a href="https://library.howplatform.net" target="_blank" rel="noopener">المكتبة الرقمية</a> حسب حاجتك.'
        ],
    ];

    // مخطط FAQPage (نص صِرف بدون HTML داخل JSON-LD)
    $faqSchema = array_map(function ($item) {
        return [
            '@type' => 'Question',
            'name'  => strip_tags($item['q']),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => strip_tags($item['a']),
            ],
        ];
    }, $faq);

    return response()->view('faq', compact('seo', 'faq', 'faqSchema'));
}

public function platformsXXX()
{
    $seo = [
        'title'       => 'منصّاتنا | منصة كيف التعليمية',
        'description' => 'تعرّف على المنصّات الفرعية لمنصة كيف التعليمية: الدبلوم العالي والمكتبة الرقمية، والمزيد قريبًا.',
        'url'         => url('/platforms'),
    ];

    // تعريف المنصّات لعرضها في الصفحة
    $platforms = [
        [
            'title' => 'منصة الدبلوم العالي',
            'desc'  => 'مواد، مهام، وإرشادات برنامج الدبلوم العالي في التربية.',
            'href'  => 'https://diploma.howplatform.net', // حسب توجيهك
        ],
        [
            'title' => 'المكتبة الرقمية',
            'desc'  => 'مصادر تعليمية منظّمة حسب الصفوف والمباحث والفصول، مع فلاتر ذكية.',
            'href'  => 'https://library.howplatform.net',
        ],
        [
            'title' => 'الأدوات الرقمية',
            'desc'  => 'منصّة موحّدة لأدوات تعليمية وخدمية صغيرة قابلة للتوسّع، تتكامل مع منصّات HOW عبر حساب واحد، لتبسيط العمل وتسريع المهام اليومية.',
            'href'  => 'https://tools.howplatform.net',
        ],

        [
            'title' => 'منصّات قادمة',
            'desc'  => 'نعمل على إطلاق منصّات متخصصة لخدمة المجتمع التعليمي، قريبًا إن شاء الله.',
            'href'  => '#',
        ],
    ];

    return response()->view('platforms', compact('seo','platforms'));
}


}
