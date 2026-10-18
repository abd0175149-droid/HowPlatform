<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Tool;
use App\Models\Category;
use App\Models\ToolStat;

class ToolsAdminController extends Controller
{
    public function index()
    {
        $tools = Tool::with(['categories:id,name', 'stats'])
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('admin.tools.index', compact('tools'));
    }

    public function create()
    {
        return view('admin.tools.form', [
            'tool'       => new Tool(['status' => 'active', 'is_active' => 1, 'is_featured' => 0]),
            'categories' => Category::orderBy('name')->get(),
            'selected'   => [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request, null);

        $tool = Tool::create($data['tool']);
        ToolStat::firstOrCreate(
            ['tool_id' => $tool->id],
            ['uses_count' => 0, 'unique_users_count' => 0, 'rating_avg' => 0, 'rating_count' => 0]
        );
        $tool->categories()->sync($data['categories']);

        return redirect()->route('admin.tools.index')->with('ok', 'تم إنشاء الأداة بنجاح.');
    }

    public function edit(Tool $tool)
    {
        return view('admin.tools.form', [
            'tool'       => $tool,
            'categories' => Category::orderBy('name')->get(),
            'selected'   => $tool->categories()->pluck('categories.id')->all(),
        ]);
    }

    public function update(Request $request, Tool $tool)
    {
        $data = $this->validateData($request, $tool->id);

        $tool->update($data['tool']);
        $tool->categories()->sync($data['categories']);

        return redirect()->route('admin.tools.index')->with('ok', 'تم تحديث الأداة بنجاح.');
    }

    public function destroy(Tool $tool)
    {
        $tool->categories()->detach();
        ToolStat::where('tool_id', $tool->id)->delete();
        $tool->delete();

        return redirect()->route('admin.tools.index')->with('ok', 'تم حذف الأداة.');
    }

    /** تبديل حقل منطقي (is_active / is_featured) سريعًا من القائمة */
    public function toggle(Request $request, Tool $tool)
    {
        $field = (string) $request->input('field');
        abort_unless(in_array($field, ['is_active', 'is_featured'], true), 422);

        $tool->update([$field => $tool->{$field} ? 0 : 1]);

        return back()->with('ok', 'تم التحديث.');
    }

    /**
     * يتحقق من المدخلات ويعيد ['tool' => [...], 'categories' => [...]].
     */
    private function validateData(Request $request, ?int $id): array
    {
        $v = $request->validate([
            'name'         => ['required', 'string', 'max:190'],
            'slug'         => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9\-]+$/', Rule::unique('tools', 'slug')->ignore($id)],
            'url'          => ['nullable', 'string', 'max:255'],
            'description'  => ['nullable', 'string', 'max:1000'],
            'image_url'    => ['nullable', 'string', 'max:255'],
            'icon_class'   => ['nullable', 'string', 'max:100'],
            'icon_color'   => ['nullable', 'string', 'max:30'],
            'gradient'     => ['nullable', 'string', 'max:190'],
            'status'       => ['nullable', 'string', 'max:30'],
            'categories'   => ['array'],
            'categories.*' => ['integer', 'exists:categories,id'],
        ], [], [
            'name' => 'الاسم',
            'slug' => 'المعرّف (slug)',
            'url'  => 'الرابط',
        ]);

        $slug = $this->uniqueSlug($v['slug'] ?? '', $v['name'], $id);

        return [
            'tool' => [
                'name'        => $v['name'],
                'slug'        => $slug,
                'url'         => ($v['url'] ?? '') !== '' ? $v['url'] : '#',
                'description' => $v['description'] ?? null,
                'image_url'   => $v['image_url'] ?? null,
                'icon_class'  => $v['icon_class'] ?? null,
                'icon_color'  => $v['icon_color'] ?? null,
                'gradient'    => $v['gradient'] ?? null,
                'status'      => $v['status'] ?? 'active',
                'is_active'   => $request->boolean('is_active') ? 1 : 0,
                'is_featured' => $request->boolean('is_featured') ? 1 : 0,
            ],
            'categories' => $v['categories'] ?? [],
        ];
    }

    /** يولّد slug فريدًا: من المدخل، أو من الاسم، أو عشوائيًا (للأسماء العربية) */
    private function uniqueSlug(string $slug, string $name, ?int $ignoreId): string
    {
        $base = $slug !== '' ? $slug : Str::slug($name);
        if ($base === '') {
            $base = 'tool-' . Str::lower(Str::random(6));
        }

        $candidate = $base;
        $i = 2;
        while (Tool::where('slug', $candidate)->when($ignoreId, fn ($q) => $q->where('id', '<>', $ignoreId))->exists()) {
            $candidate = $base . '-' . $i;
            $i++;
        }

        return $candidate;
    }
}
