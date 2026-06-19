<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    private FcmService $fcmService;

    public function __construct(FcmService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /** رد JSON UTF-8 */
    private function j($data, int $code = 200)
    {
        return response()->json($data, $code, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * تسجيل Device Token و Device ID
     * POST /api/mobile/notifications/register-device
     */
    public function registerDevice(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->j(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'device_id' => ['nullable', 'string', 'max:190'],
            'push_token' => ['required', 'string', 'max:500'],
            'platform' => ['nullable', 'string', 'in:android,ios'],
        ]);

        $deviceId = $data['device_id'] ?? null;
        $pushToken = $data['push_token'];
        $platform = $data['platform'] ?? 'android';

        // البحث عن جهاز موجود بنفس device_id أو push_token
        $existing = DB::connection('diploma')
            ->table('user_devices')
            ->where('user_id', $user->id)
            ->where(function ($query) use ($deviceId, $pushToken) {
                if ($deviceId) {
                    $query->where('device_id', $deviceId);
                }
                $query->orWhere('push_token', $pushToken);
            })
            ->first();

        if ($existing) {
            // تحديث الجهاز الموجود
            DB::connection('diploma')
                ->table('user_devices')
                ->where('id', $existing->id)
                ->update([
                    'device_id' => $deviceId ?? $existing->device_id,
                    'push_token' => $pushToken,
                    'platform' => $platform,
                    'updated_at' => now(),
                ]);

            return $this->j([
                'success' => true,
                'message' => 'Device updated',
                'device_id' => $existing->id,
            ]);
        } else {
            // إنشاء جهاز جديد
            $deviceId = DB::connection('diploma')
                ->table('user_devices')
                ->insertGetId([
                    'user_id' => $user->id,
                    'device_id' => $deviceId,
                    'push_token' => $pushToken,
                    'platform' => $platform,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            return $this->j([
                'success' => true,
                'message' => 'Device registered',
                'device_id' => $deviceId,
            ], 201);
        }
    }

    /**
     * جلب الإشعارات للمستخدم
     * GET /api/mobile/notifications
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->j(['error' => 'Unauthorized'], 401);
        }

        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);
        $unreadOnly = $request->query('unread_only', false);

        $query = DB::connection('diploma')
            ->table('notifications')
            ->where('user_id', $user->id);

        if ($unreadOnly) {
            $query->where('is_read', 0);
        }

        $total = $query->count();
        $notifications = $query
            ->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return $this->j([
            'success' => true,
            'data' => $notifications,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * تحديد الإشعار كمقروء
     * POST /api/mobile/notifications/{id}/read
     */
    public function markAsRead(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user) {
            return $this->j(['error' => 'Unauthorized'], 401);
        }

        $updated = DB::connection('diploma')
            ->table('notifications')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->update([
                'is_read' => 1,
            ]);

        if ($updated) {
            return $this->j([
                'success' => true,
                'message' => 'Notification marked as read',
            ]);
        } else {
            return $this->j([
                'error' => 'Notification not found',
            ], 404);
        }
    }

    /**
     * جلب عدد الإشعارات غير المقروءة
     * GET /api/mobile/notifications/unread-count
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->j(['error' => 'Unauthorized'], 401);
        }

        $count = DB::connection('diploma')
            ->table('notifications')
            ->where('user_id', $user->id)
            ->where('is_read', 0)
            ->count();

        return $this->j([
            'success' => true,
            'unread_count' => $count,
        ]);
    }
}

