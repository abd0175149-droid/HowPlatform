<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * إضافة حالة 'completed' لإحالات تجاوزت سقف المكافآت:
 * المدعوّ أكمل ملفه فعلاً لكن الداعي وصل حدّه الأقصى من الدعوات المُكافأة —
 * تُسجَّل الإحالة كمكتملة دون منح نقاط (تمييزًا لها عن pending وrewarded).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE referrals MODIFY status ENUM('pending','completed','rewarded') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("UPDATE referrals SET status='pending' WHERE status='completed'");
        DB::statement("ALTER TABLE referrals MODIFY status ENUM('pending','rewarded') NOT NULL DEFAULT 'pending'");
    }
};
