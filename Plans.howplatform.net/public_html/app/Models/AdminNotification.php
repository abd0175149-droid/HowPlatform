<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * إشعار لوحة الإدارة (مشترك لكل المشرفين). مقروء = read_at غير فارغ.
 */
class AdminNotification extends Model
{
    protected $table = 'admin_notifications';

    public $timestamps = false; // العمود الوحيد هو created_at (يُضبط يدويًا)

    protected $fillable = ['type', 'title', 'body', 'icon', 'color', 'link', 'read_at', 'created_at'];

    protected $casts = [
        'read_at'    => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * إنشاء إشعار — مغلّف بـ try/catch فلا يكسر المسار الحيّ (الاستبدال/المنح) أبدًا.
     * opts: body, icon, color, link.
     */
    public static function notify(string $type, string $title, array $opts = []): void
    {
        try {
            static::create([
                'type'       => $type,
                'title'      => mb_substr($title, 0, 255),
                'body'       => isset($opts['body']) && $opts['body'] !== null ? mb_substr((string) $opts['body'], 0, 500) : null,
                'icon'       => $opts['icon']  ?? 'fa-bell',
                'color'      => $opts['color'] ?? '#116580',
                'link'       => $opts['link']  ?? null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // لا تُسقط العملية الأساسية بسبب فشل إشعار
        }
    }

    public function timeAgo(): string
    {
        return optional($this->created_at)->diffForHumans() ?? '';
    }
}
