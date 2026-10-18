<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // = auth.users.id العالمي
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->enum('status', ['active', 'scheduled', 'expired', 'canceled'])->default('active');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->enum('source', ['card', 'admin', 'promo'])->default('admin');
            $table->unsignedBigInteger('card_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); // معرّف الأدمن المُفعِّل
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'end_at']);
            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
