<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_records', function (Blueprint $table) {
            // طبقة منع الازدواج على مستوى الـ API: إعادة نفس الاستجابة عند تكرار الطلب.
            $table->id();
            $table->string('service_client', 40);
            $table->string('idempotency_key', 191);
            $table->string('endpoint', 60);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamps();

            $table->unique(['service_client', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
    }
};
