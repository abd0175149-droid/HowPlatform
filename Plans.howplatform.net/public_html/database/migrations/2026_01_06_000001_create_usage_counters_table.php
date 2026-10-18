<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_counters', function (Blueprint $table) {
            // عدّاد استهلاك المزايا المحدودة بحصّة (مثل رسائل فَطين يوميًا / تحميلات المكتبة يوميًا).
            // مصدر الحقيقة لكم استهلك المستخدم من ميزة ضمن نافذة زمنية (period_key).
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            // المفتاح المسطّح للميزة كما في features_flat (مثل fateen_messages_per_day أو downloads_per_day).
            $table->string('feature_key', 100);
            // نافذة العدّ: يومي = YYYY-MM-DD (Asia/Amman)، شهري = YYYY-MM. قابلة للتوسعة.
            $table->string('period_key', 32);
            // المستهلَك ضمن هذه النافذة.
            $table->unsignedBigInteger('used')->default(0);
            $table->timestamps();

            // مفتاح فريد = حاجز التسلسل لكل (مستخدم، ميزة، نافذة) → قفل صفّ واحد يُسلسِل العدّ.
            $table->unique(['user_id', 'feature_key', 'period_key'], 'uniq_usage_user_feature_period');
            $table->index(['feature_key', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counters');
    }
};
