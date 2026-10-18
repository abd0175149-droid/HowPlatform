<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            // مزايا الخطة عبر كل المنصات: لكل ميزة منصّة + مفتاح + قيمة.
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('platform', 40);     // diploma | tools | library | www | all
            $table->string('feature_key', 100); // مثل fateen_messages_per_day, classes_allowed, downloads_per_day
            $table->string('label', 191)->nullable();
            $table->string('value', 191)->nullable(); // قيمة بسيطة (عدد/نص). للقيم المركّبة استخدم value_json
            $table->json('value_json')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'platform', 'feature_key']);
            $table->index(['platform', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
