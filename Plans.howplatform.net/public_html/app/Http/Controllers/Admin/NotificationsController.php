<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->string('filter')->value(); // '' | unread
        $q = AdminNotification::query()->orderByDesc('id');
        if ($filter === 'unread') {
            $q->whereNull('read_at');
        }

        return view('admin.notifications.index', [
            'items'  => $q->paginate(30)->withQueryString(),
            'filter' => $filter,
            'unread' => AdminNotification::whereNull('read_at')->count(),
            'total'  => AdminNotification::count(),
        ]);
    }

    /** تبديل حالة القراءة (مقروء ↔ غير مقروء). */
    public function read(AdminNotification $notification)
    {
        $notification->update(['read_at' => $notification->read_at ? null : now()]);
        return back();
    }

    public function markAllRead()
    {
        AdminNotification::whereNull('read_at')->update(['read_at' => now()]);
        return back()->with('ok', 'تم تمييز كل الإشعارات كمقروءة.');
    }

    public function destroy(AdminNotification $notification)
    {
        $notification->delete();
        return back()->with('ok', 'تم حذف الإشعار.');
    }

    /** حذف المقروءة (افتراضي) أو الكل (scope=all). */
    public function clear(Request $request)
    {
        if ($request->input('scope') === 'all') {
            AdminNotification::query()->delete();
            return back()->with('ok', 'تم حذف كل الإشعارات.');
        }
        AdminNotification::whereNotNull('read_at')->delete();
        return back()->with('ok', 'تم حذف الإشعارات المقروءة.');
    }
}
