<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // تتابع الدخول اليومي (مفتاح = auth.users.id)
        Schema::create('streaks', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->unsignedSmallInteger('current_streak')->default(0);
            $table->date('last_login_date')->nullable();
            $table->timestamps();
        });

        // الإحالات
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id');
            $table->unsignedBigInteger('referred_id')->unique(); // كل مدعوّ له داعٍ واحد
            $table->enum('status', ['pending', 'rewarded'])->default('pending');
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();
            $table->index('referrer_id');
        });

        // أكواد الإحالة
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->string('code', 20)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_codes');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('streaks');
    }
};
