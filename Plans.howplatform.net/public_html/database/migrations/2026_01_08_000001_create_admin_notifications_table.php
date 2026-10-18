<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table) {
            // إشعارات لوحة الإدارة (مشتركة لكل المشرفين). مقروء = read_at غير فارغ.
            $table->bigIncrements('id');
            $table->string('type', 40)->default('info');   // card_redeemed | subscription | card_issued | sale | info
            $table->string('title', 255);
            $table->string('body', 500)->nullable();
            $table->string('icon', 40)->default('fa-bell');
            $table->string('color', 20)->default('#116580');
            $table->string('link', 255)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index(['read_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
