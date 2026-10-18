<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حقول البائع/المشتري على البطاقة.
 * - بطاقات الأدمن المجمّعة (batch): issued_by = null، بلا مشتري/سعر.
 * - بطاقات البائع (POS): issued_by = المندوب، مع بيانات المشتري والسعر والمدّة.
 * duration_days يسمح للبائع بتحديد مدّة مختلفة عن مدّة الخطة الافتراضية.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->unsignedBigInteger('issued_by')->nullable()->after('created_by')->index(); // user_id المندوب
            $table->string('buyer_name')->nullable()->after('issued_by');
            $table->string('buyer_email')->nullable()->after('buyer_name');
            $table->string('buyer_phone', 30)->nullable()->after('buyer_email');
            $table->decimal('price_amount', 10, 2)->nullable()->after('buyer_phone');
            $table->string('price_currency', 10)->default('JOD')->after('price_amount');
            $table->unsignedSmallInteger('duration_days')->nullable()->after('price_currency'); // override مدّة الخطة
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn([
                'issued_by', 'buyer_name', 'buyer_email', 'buyer_phone',
                'price_amount', 'price_currency', 'duration_days',
            ]);
        });
    }
};
