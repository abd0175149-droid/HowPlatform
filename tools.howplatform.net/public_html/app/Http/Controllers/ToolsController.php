<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tool;
use App\Models\Category;

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

}
