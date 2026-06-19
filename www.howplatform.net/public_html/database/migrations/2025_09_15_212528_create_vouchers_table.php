<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('platform', 50);
            $table->unsignedBigInteger('platform_plan_id');
            $table->string('plan_name')->nullable();
            $table->decimal('price_amount', 10, 2)->default(0);
            $table->string('price_currency', 10)->default('JOD');
            $table->unsignedInteger('duration_days')->default(30);
            $table->string('buyer_name');
            $table->string('buyer_email')->nullable();
            $table->string('buyer_phone')->nullable();
            $table->text('notes')->nullable();
            $table->string('verify_url')->nullable();
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['platform','platform_plan_id']);
            $table->index('expires_at');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
