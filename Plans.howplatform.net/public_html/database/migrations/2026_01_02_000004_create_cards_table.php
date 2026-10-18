<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            // بطاقة تُباع للمستخدم: تُفعّل اشتراكًا و/أو تمنح نقاطًا عند الاستبدال.
            $table->id();
            $table->string('code', 40)->unique();
            $table->enum('type', ['subscription', 'points', 'mixed'])->default('subscription');
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->unsignedInteger('points')->default(0);
            $table->enum('status', ['active', 'redeemed', 'disabled'])->default('active');
            $table->string('batch', 60)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('redeemed_by')->nullable(); // user_id الذي استبدلها
            $table->timestamp('redeemed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();   // معرّف الأدمن المُصدِر
            $table->timestamps();

            $table->index(['status', 'batch']);
            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
