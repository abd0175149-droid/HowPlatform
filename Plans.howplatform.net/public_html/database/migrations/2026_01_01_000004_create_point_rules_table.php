<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_rules', function (Blueprint $table) {
            // كتالوج الاقتصاد: الخادم هو مرجع المبالغ (العميل لا يرسل المبلغ للقواعد المعروفة).
            $table->id();
            $table->string('event_key', 100)->unique(); // مثل diploma.referral.completed
            $table->string('kind', 20);                  // earn | spend
            $table->integer('points');                   // المبلغ الافتراضي
            $table->boolean('is_active')->default(true);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_rules');
    }
};
