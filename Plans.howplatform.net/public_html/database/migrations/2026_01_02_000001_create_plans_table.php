<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->unsignedTinyInteger('level')->default(1); // رتبة الخطة (للمقارنة)
            $table->decimal('price', 10, 2)->default(0);
            $table->char('currency', 3)->default('JOD');
            $table->unsignedInteger('duration_days')->default(30);
            $table->unsignedInteger('grant_points')->default(0); // نقاط تُمنح عند التفعيل
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
