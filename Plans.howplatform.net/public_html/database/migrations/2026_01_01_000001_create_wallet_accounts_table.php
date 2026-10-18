<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_accounts', function (Blueprint $table) {
            $table->id();
            // = auth.users.id (الهوية العالمية). لا جدول مستخدمين محلّي. صفّ واحد لكل مستخدم.
            $table->unsignedBigInteger('user_id')->unique();
            // الرصيد = نسخة مخزّنة (snapshot) للحقيقة في wallet_ledger؛ النقاط لا تنزل تحت الصفر منطقيًا.
            $table->bigInteger('balance')->default(0);
            // عدّاد تفاؤلي مساعد + للمطابقة.
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_accounts');
    }
};
