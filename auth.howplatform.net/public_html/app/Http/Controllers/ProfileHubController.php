<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ProfileHub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response; // إضافة هذا الاستخدام
use Illuminate\Support\Facades\Storage; 

class ProfileHubController extends Controller
{
    public function __construct(private ProfileHub $hub){}

    /** صفحة الهَب (واجهة تبويبات + بطاقة بيانات auth الأساسية) */
    public function index(Request $request): View
    {
        return view('profile.index', [
            'user' => $request->user(),
        ]);
    }

    /** جلب الأقسام الديناميكية كـ JSON */
    public function sections(Request $request): Response // تحديد نوع الإرجاع
    {
        // استخدام دالة مساعدة لجلب الـ JWT
        $jwt = $this->getJwtToken($request);
        if (!$jwt) {
            return response()->json([
                'success' => false,
                'message' => 'JWT token not found or invalid.',
                'sections' => []
            ], 401); // 401 Unauthorized
        }

        $sections = $this->hub->fetchSections($jwt);

        // يمكن تحسين هذا للتحقق مما إذا كانت هناك أخطاء جوهرية في fetchSections
        // حالياً، الخدمة ترجع [] في حالة الفشل، لذا يمكن التعامل معها هنا إذا كانت فارغة
        if (empty($sections)) {
             return response()->json([
                'success' => true, // أو false إذا كان الفشل بسبب عدم وجود بيانات بالأساس
                'message' => 'No sections found or failed to fetch sections from platforms.',
                'sections' => []
            ]);
        }

        return response()->json([
            'success'=>true,
            'sections'=>$sections,
        ]);
    }

    /** حفظ بيانات auth الأساسية (اسم/إيميل) */
    public function updateAuth(ProfileUpdateRequest $request)
    {
        // املأ البيانات ما عدا الصورة
        $user = $request->user();
        $user->fill($request->except('avatar'));

        // معالجة رفع الصورة إذا كانت موجودة
        if ($request->hasFile('avatar')) {
            // حذف الصورة القديمة إذا كانت موجودة وكانت ملفًا محليًا
            if ($user->avatar && str_starts_with($user->avatar, asset('storage'))) {
                // استخراج المسار النسبي من الـ URL الكامل
                $oldPath = str_replace(asset(''), '', $user->avatar); // "storage/avatars/old.jpg"
                $oldPath = substr($oldPath, 1); // "storage/avatars/old.jpg" -> "torage/..." -> remove "public/" prefix before check
                if (Storage::disk('public')->exists(str_replace('storage/', '', $oldPath))) {
                     Storage::disk('public')->delete(str_replace('storage/', '', $oldPath));
                }
            }
            
            // تخزين الصورة الجديدة
            $path = $request->file('avatar')->store('avatars', 'public');

            // --- التعديل الرئيسي هنا ---
            // إنشاء وحفظ الـ URL الكامل بدلاً من المسار النسبي
            $user->avatar = asset('storage/' . $path);
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.index')->with('status', 'profile-updated');
    }

    /** تمرير حفظ قسم لمنصّة محددة */
    public function saveSection(string $platform, Request $request): Response // تحديد نوع الإرجاع
    {
        $jwt = $this->getJwtToken($request);
        if (!$jwt) {
            return response()->json([
                'success' => false,
                'message' => 'JWT token not found or invalid.',
                'errors' => ['authentication' => 'JWT token required.']
            ], 401);
        }

        $out = $this->hub->forwardSave($platform, $request->all(), $jwt);
        return response()->json($out, $out['success'] ? 200 : ($out['status'] ?? 422));
    }

    /** حذف حساب auth (كما كان) */
    public function destroy(Request $request): \Illuminate\Http\RedirectResponse // تحديد نوع الإرجاع
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * دالة مساعدة لاستخراج JWT
     */
    private function getJwtToken(Request $request): ?string
    {
        return $request->bearerToken() ?? $request->cookie('howplatform_token');
    }
}