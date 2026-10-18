<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SpinnerController extends Controller
{
    private const TOOL_ID = 4; // Tool Stats

    private function currentUserId(): ?int {
        $auth = session('auth_user');
        if (is_array($auth) && isset($auth['id'])) return (int) $auth['id'];
        if (is_object($auth) && isset($auth->id))   return (int) $auth->id;
        return null;
    }

    private function ensureOwnedSession(int $uid, int $sessionId) {
        $row = DB::table('spinner_sessions as s')
            ->leftJoin('user_classes as c', 'c.id', '=', 's.class_id')
            ->where('s.id', $sessionId)->where('s.user_id', $uid)
            ->select('s.*','c.name as class_name','c.grade_level')->first();
        abort_if(!$row, 404);
        return $row;
    }

    private function userClasses(int $uid) {
        return DB::table('user_classes as c')
            ->leftJoin('user_class_students as u', 'u.class_id', '=', 'c.id')
            ->select('c.id','c.name','c.grade_level', DB::raw('COUNT(u.id) as students_count'))
            ->where('c.user_id', $uid)
            ->groupBy('c.id','c.name','c.grade_level')
            ->orderBy('c.created_at','desc')
            ->get();
    }

    public function index(Request $request) {
        $uid = $this->currentUserId();
        

        // تذكير: إن لم يملك صفوفًا → بطاقة دعوة لإضافة صفوف
        $classes = $this->userClasses($uid);

        $rows = DB::table('spinner_sessions as s')
            ->leftJoin('user_classes as c','c.id','=','s.class_id')
            ->select('s.id','s.title','s.slug','s.status','s.created_at','c.name as class_name')
            ->where('s.user_id',$uid)
            ->orderBy('s.created_at','desc')
            ->get();

        return view('spinners.index', compact('rows','classes'));
    }

    public function create(Request $request) {
        $uid = $this->currentUserId();
        

        try {
            DB::statement('ALTER TABLE spinner_sessions MODIFY class_id BIGINT UNSIGNED NULL;');
        } catch (\Exception $e) {}

        $classes = $this->userClasses($uid);
        return view('spinners.create', compact('classes'));
    }

    public function store(Request $request) {
        $uid = $this->currentUserId();

        $data = $request->validate([
            'input_mode'     => ['required', 'in:class,manual'],
            'class_id'       => ['nullable', 'integer', 'min:1'],
            'names_text'     => ['nullable', 'string', 'max:10000'],
            'save_as_class'  => ['nullable', 'boolean'],
            'new_class_name' => ['nullable', 'string', 'max:190'],
            'new_class_grade'=> ['nullable', 'string', 'max:190'],
            
            'title'          => ['nullable', 'string', 'max:190'],
            'spin_ms'        => ['nullable', 'integer', 'min:5000', 'max:15000'],
            'remove_rule'    => ['nullable', 'in:none,remove_selected,remove_others'],
            'sound_on'       => ['nullable', 'in:0,1'],
        ], [
            'class_id.required_if' => 'اختر الصف الذي ستبنى عليه العجلة.',
            'names_text.required_if' => 'الرجاء إدخال الأسماء.',
        ]);

        $inputMode = $data['input_mode'];
        $classId = null;
        $className = 'عجلة مخصصة';
        $studentNames = [];

        if ($inputMode === 'class') {
            if (empty($data['class_id'])) {
                return back()->withErrors(['class_id' => 'يجب اختيار صف.'])->withInput();
            }
            $owned = DB::table('user_classes')->where('id', $data['class_id'])->where('user_id', $uid)->first();
            if (!$owned) return back()->withErrors(['class_id' => 'هذا الصف غير موجود أو لا تملكه.'])->withInput();
            
            $classId = $owned->id;
            $className = $owned->name;
            
            $stu = DB::table('user_class_students')->where('class_id', $classId)
                ->select('id','student_name')->orderBy('id')->get();
            foreach ($stu as $s) {
                $studentNames[] = [
                    'student_id' => $s->id,
                    'label' => $s->student_name ?: ('طالب #'.$s->id)
                ];
            }
        } else {
            // Manual input
            if (empty($data['names_text'])) {
                return back()->withErrors(['names_text' => 'الرجاء إدخال الأسماء.'])->withInput();
            }
            $parsedNames = app(\App\Services\StudentImportService::class)->parseTextarea($data['names_text']);
            if (empty($parsedNames)) {
                return back()->withErrors(['names_text' => 'لم يتم التعرف على أي أسماء صالحة.'])->withInput();
            }

            if (!empty($data['save_as_class'])) {
                $reqClassName = trim($data['new_class_name'] ?? '');
                if ($reqClassName === '') {
                    return back()->withErrors(['new_class_name' => 'يجب إدخال اسم الصف.'])->withInput();
                }
                
                $className = $reqClassName;
                $classId = DB::table('user_classes')->insertGetId([
                    'user_id' => $uid,
                    'name' => $className,
                    'grade_level' => trim($data['new_class_grade'] ?? ''),
                    'academic_year' => '2025/2026',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($parsedNames as $name) {
                    $stuId = DB::table('user_class_students')->insertGetId([
                        'class_id' => $classId,
                        'student_name' => $name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $studentNames[] = [
                        'student_id' => $stuId,
                        'label' => $name
                    ];
                }
            } else {
                foreach ($parsedNames as $name) {
                    $studentNames[] = [
                        'student_id' => null,
                        'label' => $name
                    ];
                }
            }
        }

        // Title fallback
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            $seq = (int) DB::table('spinner_sessions')->where('user_id', $uid)->count() + 1;
            $title = 'العجلة الدوارة - ' . $className . ' - ' . $seq;
        }

        $settings = [
            'spin_ms'     => (int)($data['spin_ms'] ?? 8000),
            'remove_rule' => $data['remove_rule'] ?? 'none',
            'sound_on'    => (int)($data['sound_on'] ?? 1),
        ];

        $slug = Str::slug($title . '-' . Str::random(5));

        $sid = DB::table('spinner_sessions')->insertGetId([
            'user_id'       => $uid,
            'class_id'      => $classId,
            'title'         => $title,
            'slug'          => $slug,
            'settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            'status'        => 'active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        if (!empty($studentNames)) {
            $rows = [];
            foreach ($studentNames as $sn) {
                $rows[] = [
                    'session_id' => $sid,
                    'student_id' => $sn['student_id'],
                    'label'      => $sn['label'],
                    'is_active'  => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('spinner_items')->insert($rows);
        }

        return redirect()->route('user.spinners.show', $sid)->with('ok','تم إنشاء العجلة بنجاح.');
    }

    public function show(Request $request, int $id) {
        $uid = $this->currentUserId();
        
        $session = $this->ensureOwnedSession($uid, $id);

        $items = DB::table('spinner_items')->where('session_id',$id)->orderBy('id')->get();

        // إعدادات
        $settings = json_decode($session->settings_json ?? '{}', true) ?: [];
        $spinMs   = (int)($settings['spin_ms'] ?? 8000);
        $remove   = (string)($settings['remove_rule'] ?? 'none');
        $soundOn  = (int)($settings['sound_on'] ?? 1);

        // زيادة استخدام الأداة (atomic)
        DB::table('tool_stats')->updateOrInsert(['tool_id'=>self::TOOL_ID], [
            'uses_count'  => DB::raw('uses_count + 1'),
            'last_used_at'=> now(),
            'updated_at'  => now(),
        ]);

        return view('spinners.show', [
            'session' => $session,
            'items'   => $items,
            'spinMs'  => $spinMs,
            'removeRule' => $remove,
            'soundOn' => $soundOn,
            'shareUrl' => url('/my/spinners/'.$id), // للمشاركة و QR
        ]);
    }

    public function itemsAdd(Request $request, int $id) {
        $uid = $this->currentUserId();
        if (!$uid) return response()->json(['ok'=>false,'error'=>'unauthorized'], 401);
        $this->ensureOwnedSession($uid, $id);

        $data = $request->validate([
            'labels'   => ['required','string','max:5000'], // سطر لكل اسم
        ]);
        $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $data['labels'])));
        if (!$lines) return response()->json(['ok'=>false,'error'=>'empty']);

        $rows = [];
        foreach ($lines as $label) {
            $rows[] = [
                'session_id' => $id,
                'student_id' => null,
                'label'      => $label,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('spinner_items')->insert($rows);
        return response()->json(['ok'=>true]);
    }

    public function itemsToggle(Request $request, int $id) {
        $uid = $this->currentUserId();
        if (!$uid) return response()->json(['ok'=>false,'error'=>'unauthorized'], 401);
        $this->ensureOwnedSession($uid, $id);

        $data = $request->validate([
            'item_id' => ['required','integer','min:1'],
            'active'  => ['required','in:0,1'],
        ]);
        DB::table('spinner_items')->where('id',$data['item_id'])->where('session_id',$id)
            ->update(['is_active'=>(int)$data['active'],'updated_at'=>now()]);
        return response()->json(['ok'=>true]);
    }

    public function itemsClear(Request $request, int $id) {
        $uid = $this->currentUserId();
        if (!$uid) return response()->json(['ok'=>false,'error'=>'unauthorized'], 401);
        $this->ensureOwnedSession($uid, $id);

        $data = $request->validate([
            'mode' => ['required','in:remove_selected,remove_others'],
            'selected_label' => ['nullable','string','max:190'],
        ]);

        if ($data['mode']==='remove_selected' && ($data['selected_label'] ?? '')!=='') {
            DB::table('spinner_items')
              ->where('session_id',$id)->where('label',$data['selected_label'])
              ->update(['is_active'=>0,'updated_at'=>now()]);
        } elseif ($data['mode']==='remove_others' && ($data['selected_label'] ?? '')!=='') {
            DB::table('spinner_items')
              ->where('session_id',$id)->where('label','<>',$data['selected_label'])
              ->update(['is_active'=>0,'updated_at'=>now()]);
        }
        return response()->json(['ok'=>true]);
    }

    public function saveSettings(Request $request, int $id) {
        $uid = $this->currentUserId();
        if (!$uid) return response()->json(['ok'=>false,'error'=>'unauthorized'], 401);
        $session = $this->ensureOwnedSession($uid, $id);

        $data = $request->validate([
            'spin_ms'     => ['required','integer','min:1000','max:30000'],
            'remove_rule' => ['required','in:none,remove_selected,remove_others'],
            'sound_on'    => ['required','in:0,1'],
        ]);

        $settings = json_decode($session->settings_json ?? '{}', true) ?: [];
        $settings['spin_ms']     = (int)$data['spin_ms'];
        $settings['remove_rule'] = $data['remove_rule'];
        $settings['sound_on']    = (int)$data['sound_on'];

        DB::table('spinner_sessions')->where('id',$id)->update([
            'settings_json'=> json_encode($settings, JSON_UNESCAPED_UNICODE),
            'updated_at'   => now(),
        ]);
        return response()->json(['ok'=>true]);
    }

    public function spin(Request $request, int $id) {
        $uid = $this->currentUserId();
        if (!$uid) return response()->json(['ok'=>false,'error'=>'unauthorized'], 401);
        $session = $this->ensureOwnedSession($uid, $id);

        // جلب العناصر الفعالة فقط
        $items = DB::table('spinner_items')->where('session_id',$id)->where('is_active',1)->pluck('label','id')->all();
        if (count($items) < 1) return response()->json(['ok'=>false,'error'=>'لا توجد عناصر مفعّلة']);

        // اختيار عشوائي موحّد وغير متحيّز عبر CSPRNG (بدل crc32 الضعيف والمتحيّز بـ modulo)
        $seed = 'spin-'.round(microtime(true)*1000);
        $keys = array_keys($items);
        $idx  = random_int(0, count($keys) - 1);
        $itemId = $keys[$idx];
        $label  = $items[$itemId];

        // قراءة إعدادات الحذف
        $settings = json_decode($session->settings_json ?? '{}', true) ?: [];
        $remove   = (string)($settings['remove_rule'] ?? 'none');
        $spinMs   = (int)($settings['spin_ms'] ?? 8000);

        DB::table('spinner_results')->insert([
            'session_id'     => $id,
            'item_id'        => $itemId,
            'label_snapshot' => $label,
            'spin_ms'        => $spinMs,
            'remove_rule'    => $remove,
            'seed'           => $seed,
            'meta_json'      => null,
            'landed_at'      => now(),
        ]);

        // تطبيق الحذف حسب الإعداد عند نهاية اللفة — (العميل قد يستدعي itemsClear مباشرة، لكن نجعلها هنا أيضًا لضمان الاتساق)
        if ($remove === 'remove_selected') {
            DB::table('spinner_items')->where('id',$itemId)->update(['is_active'=>0,'updated_at'=>now()]);
        } elseif ($remove === 'remove_others') {
            DB::table('spinner_items')->where('session_id',$id)->where('id','<>',$itemId)->update(['is_active'=>0,'updated_at'=>now()]);
        }

        return response()->json(['ok'=>true,'winner'=>['id'=>$itemId,'label'=>$label]]);
    }

    public function destroyPost(Request $request, int $id) {
        return $this->destroy($request, $id);
    }
    public function destroy(Request $request, int $id) {
        $uid = $this->currentUserId();
        
        $this->ensureOwnedSession($uid, $id);
        DB::table('spinner_sessions')->where('id',$id)->delete();
        return redirect()->route('user.spinners.index')->with('ok','تم حذف العجلة وكافة بياناتها.');
    }

    // === تقييم الأداة (ID=4) ===
    public function rate(Request $request) {
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
        ]);
        DB::table('tool_stats')->updateOrInsert(['tool_id'=>self::TOOL_ID], [
            'rating_avg'   => DB::raw('(rating_avg * rating_count + '.(int)$data['rating'].') / (rating_count + 1)'),
            'rating_count' => DB::raw('rating_count + 1'),
            'updated_at'   => now(),
        ]);
        return response()->json(['ok'=>true]);
    }
}
