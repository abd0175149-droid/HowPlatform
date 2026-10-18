<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Tool;
use App\Models\Category;
use App\Models\ToolStat;

class ToolsController extends Controller
{
    /**
     * تبويبات/فلاتر مدعومة:
     * all | writing | analytics | classroom | fun | media
     * featured | most-used | most-popular | new
     */
public function index(Request $request)
{
    $tab = $request->string('tab')->lower()->value() ?: 'all';

    // للتبويب الجانبي/العناوين
    $categories = Category::orderBy('name')->get(['id','name','slug']);

    // الاستعلام الأساسي
    $q = Tool::query()
        ->with(['categories:id,name,slug','stats'])
        ->where('is_active', 1);

    // تبويبات ثابتة مدعومة
    $fixedTabs = [
        'all','writing','analytics','classroom','fun','media',
        'featured','most-used','most-popular','new'
    ];

    // ✅ دعم أي تصنيف جديد تلقائيًا: إن لم يكن من التبويبات الثابتة وكان slug لتصنيف موجود
    if ($tab && !in_array($tab, $fixedTabs, true) && Category::where('slug', $tab)->exists()) {
        $q->whereHas('categories', fn($qq) => $qq->where('slug', $tab))
          ->orderByDesc('tools.created_at');
    } else {
        // التبويبات الثابتة المعتادة
        switch ($tab) {
            case 'writing':
            case 'analytics':
            case 'classroom':
            case 'fun':
            case 'media':
                $q->whereHas('categories', fn($qq) => $qq->where('slug', $tab))
                  ->orderByDesc('tools.created_at');
                break;

            case 'featured':
                $q->where('is_featured', 1)
                  ->orderByDesc('tools.created_at');
                break;

            case 'most-used':
                $q->leftJoin('tool_stats', 'tools.id', '=', 'tool_stats.tool_id')
                  ->select('tools.*')
                  ->orderByDesc('tool_stats.uses_count')
                  ->orderByDesc('tools.created_at');
                break;

            case 'most-popular':
                $q->leftJoin('tool_stats', 'tools.id', '=', 'tool_stats.tool_id')
                  ->select('tools.*')
                  ->orderByDesc('tool_stats.rating_avg')
                  ->orderByDesc('tool_stats.rating_count')
                  ->orderByDesc('tool_stats.uses_count')
                  ->orderByDesc('tools.created_at');
                break;

            case 'new':
            case 'all':
            default:
                $q->orderByDesc('tools.created_at');
                break;
        }
    }

    $tools = $q->paginate(18)->withQueryString();
    return view('tools.index', [
        'tools'      => $tools,
        'categories' => $categories,
        'activeTab'  => $tab,
    ]);
}

    /**
     * صفحة تفاصيل أداة (هبوط SEO): الوصف + التصنيفات + التقييم + أدوات ذات صلة + JSON-LD.
     */
    public function show(string $slug)
    {
        $tool = Tool::with(['categories:id,name,slug', 'stats'])
            ->where('slug', $slug)
            ->where('is_active', 1)
            ->firstOrFail();

        $related = Tool::with(['categories:id,name,slug', 'stats'])
            ->where('is_active', 1)
            ->where('id', '<>', $tool->id)
            ->when($tool->categories->isNotEmpty(), function ($q) use ($tool) {
                $q->whereHas('categories', fn ($qq) => $qq->whereIn('categories.id', $tool->categories->pluck('id')));
            })
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        return view('tools.show', compact('tool', 'related'));
    }

    /**
     * رابط انتقال متتبَّع: يزيد عدّاد الاستخدام ثم يحوّل لوجهة الأداة الفعلية.
     * إن لم تكن للأداة وجهة (url = '#') يحوّل لصفحة التفاصيل.
     */
    public function go(string $slug)
    {
        $tool = Tool::where('slug', $slug)->where('is_active', 1)->firstOrFail();

        // زيادة عدّاد الاستخدام (ينشئ صف الإحصاء إن لم يوجد)
        $stat = ToolStat::firstOrNew(['tool_id' => $tool->id]);
        $stat->uses_count   = (int) ($stat->uses_count ?? 0) + 1;
        $stat->last_used_at = now();
        $stat->save();

        $hasTarget = $tool->url && trim($tool->url) !== '' && $tool->url !== '#';
        return $hasTarget
            ? redirect()->away($tool->url)
            : redirect()->route('tools.show', $tool->slug);
    }

    /**
     * API عام (JSON): قائمة الأدوات المفعّلة لتستهلكها منصّات أخرى
     * (مثل "الأدوات التعليمية" في داشبورد الطلاب بمنصّة الدبلوم).
     * مخزَّن مؤقتًا 5 دقائق. الروابط: href = رابط انتقال متتبَّع (go) يزيد عدّاد الاستخدام.
     */
    public function api()
    {
        // قاعدة ثابتة للروابط (لا تعتمد على مضيف الطلب — كي تصحّ حتى لو مُلئ الكاش عبر نداء داخلي 127.0.0.1)
        $base = rtrim((string) config('app.url'), '/');
        if ($base === '' || str_contains($base, 'localhost') || str_contains($base, '127.0.0.1')) {
            $base = 'https://tools.howplatform.net';
        }
        $base = preg_replace('#^http://#', 'https://', $base);

        $tools = Cache::remember('api.tools.list.v3', 300, function () use ($base) {
            return Tool::query()
                ->where('is_active', 1)
                // الأدوات ذات وجهة فعلية أولًا؛ أدوات "قريبًا" (بلا url) تبقى في الآخر دائمًا
                ->orderByRaw("CASE WHEN `url` IS NULL OR `url` = '' OR `url` = '#' THEN 1 ELSE 0 END ASC")
                ->orderByDesc('is_featured')
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'slug', 'url', 'image_url', 'icon_class', 'icon_color', 'gradient', 'description', 'is_featured'])
                ->map(function ($t) use ($base) {
                    $hasTarget = $t->url && trim((string) $t->url) !== '' && $t->url !== '#';
                    return [
                        'name'        => (string) $t->name,
                        'slug'        => (string) $t->slug,
                        'description' => (string) ($t->description ?? ''),
                        'icon_class'  => (string) ($t->icon_class ?? ''),
                        'icon_color'  => (string) ($t->icon_color ?? ''),
                        'gradient'    => (string) ($t->gradient ?? ''),
                        'image_url'   => (string) ($t->image_url ?? ''),
                        'url'         => $hasTarget ? (string) $t->url : null,
                        'go_url'      => $base . '/go/' . $t->slug,
                        'detail_url' => $base . '/t/' . $t->slug,
                        'href'        => $base . '/go/' . $t->slug,
                        'is_featured' => (int) $t->is_featured,
                    ];
                })
                ->values();
        });

        return response()
            ->json(['tools' => $tools])
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
