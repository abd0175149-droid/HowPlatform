<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_packages', function (Blueprint $table) {
            // سلّم تسعير النقاط: كل صف = حزمة نقاط لها سعر ثابت عند البيع.
            // يُستخدَم عند إصدار بطاقات النقاط (أدمن/بائع) لربط عدد النقاط بسعرها.
            $table->bigIncrements('id');
            $table->string('label', 80)->nullable();          // اسم عرضي اختياري (مثل: حزمة 500 نقطة)
            $table->unsignedInteger('points');                 // عدد النقاط
            $table->decimal('price_amount', 10, 2);            // السعر
            $table->string('price_currency', 10)->default('JOD');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_packages');
    }
};
