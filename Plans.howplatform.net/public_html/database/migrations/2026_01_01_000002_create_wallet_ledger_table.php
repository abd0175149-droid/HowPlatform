<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_ledger', function (Blueprint $table) {
            // دفتر قيود غير قابل للتعديل (append-only): هو مصدر الحقيقة.
            // التطبيق لا يُحدِّث/يحذف أبدًا؛ trigger منع UPDATE/DELETE يُضاف في مرحلة التصليب (P7).
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            // موجب = كسب، سالب = صرف. الصفر = استهلاك وحدة مجانية (free_unit).
            $table->bigInteger('points');
            // رصيد المحفظة بعد تطبيق هذا القيد (للتدقيق والمطابقة).
            $table->bigInteger('balance_after');
            // earn | spend | refund | adjust | free_unit
            $table->string('kind', 20);
            // مفتاح منع الازدواج الحتمي (مثل diploma:spend:job:123). فريد عالميًا = الحاجز المالي.
            $table->string('idempotency_key', 191)->unique();
            // وصف الحدث الاقتصادي (مثل diploma.aitool.spend) ومرجعه.
            $table->string('event_type', 100)->nullable();
            $table->string('event_ref_type', 40)->nullable();
            $table->string('event_ref_id', 64)->nullable();
            // أي منصّة بدأت العملية.
            $table->string('service_client', 40);
            $table->string('description', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'id']);
            $table->index(['event_type', 'event_ref_type', 'event_ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_ledger');
    }
};
