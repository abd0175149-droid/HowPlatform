<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_catalog', function (Blueprint $table) {
            // كتالوج المزايا المتاحة (مُستخرَج من كود كل منصّة). الأدمن يختار منها عند بناء الخطة.
            $table->id();
            $table->string('platform', 40);      // diploma | tools | library | www | all
            $table->string('feature_key', 100);  // مفتاح نظيف (ASCII): fateen_messages_per_day ...
            $table->string('label', 191);        // الاسم المعروض (عربي)
            $table->string('value_type', 20)->default('int'); // int | select | bool | text
            $table->string('unit', 40)->nullable();           // وحدة (رسالة/يوم، صف...)
            $table->json('options')->nullable();              // خيارات select
            $table->string('default_value', 100)->nullable();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['platform', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_catalog');
    }
};
