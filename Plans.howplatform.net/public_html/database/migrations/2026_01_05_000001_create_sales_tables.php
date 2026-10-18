<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * طبقة البائعين (POS) في Plans — مُرحَّلة من www.howplatform.net.
 * البائع يُصدر بطاقة Plans للمشتري وتُسجَّل عملية البيع على رصيده الشهري،
 * مع سقف شهري، وعمولة (خصم %)، ومطالبات (فواتير) ومدفوعات للتحصيل.
 *
 * فرق جوهري عن www: المرجع هو cards.id (بطاقة Plans محلّية) بدل voucher عبر منصّة فرعية.
 */
return new class extends Migration
{
    public function up(): void
    {
        // عدّاد أكواد المستندات (TX-000001 / PAY-000001 / CLM-000001 ...)
        if (! Schema::hasTable('doc_counters')) {
            Schema::create('doc_counters', function (Blueprint $table) {
                $table->id();
                $table->string('prefix', 16)->unique();
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestamps();
            });
        }

        // نقطة بيع المندوب (متجر/فرع) + رصيده الشهري الجاري
        if (! Schema::hasTable('sales_points')) {
            Schema::create('sales_points', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();      // user_id العالمي (auth)
                $table->string('user_name')->nullable();
                $table->string('store_name')->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('location_url')->nullable();
                $table->enum('sell_method', ['online', 'in_person', 'both'])->default('both');
                $table->decimal('discount_percent', 5, 2)->default(0); // عمولة المندوب %
                $table->string('governorate', 60)->nullable();
                $table->string('address_details')->nullable();
                $table->string('image_url')->nullable();
                $table->decimal('balance', 12, 2)->default(0);         // المستحق على المندوب هذا الشهر
                $table->char('balance_month_key', 7)->nullable();      // Y-m
                $table->timestamps();
            });
        }

        // السقف الشهري لكل مندوب (0 = بلا سقف)
        if (! Schema::hasTable('sales_limits')) {
            Schema::create('sales_limits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->decimal('monthly_limit', 12, 2)->default(0);
                $table->timestamps();
            });
        }

        // حركة محاسبية: بيع بطاقة / دفعة / ردّ / تعديل
        if (! Schema::hasTable('sales_transactions')) {
            Schema::create('sales_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();          // المندوب
                $table->unsignedBigInteger('card_id')->nullable()->index(); // البطاقة المُباعة (إن وُجدت)
                $table->unsignedBigInteger('applies_claim_id')->nullable()->index(); // دفعة مرتبطة بمطالبة
                $table->string('doc_code', 24)->index();
                $table->enum('type', ['card_sale', 'payment', 'refund', 'adjustment', 'adjust_invoice'])->index();
                $table->string('method', 40)->nullable();                // وسيلة الدفع (اختياري)
                $table->decimal('amount', 12, 2)->default(0);
                $table->decimal('balance_before', 12, 2)->default(0);
                $table->decimal('balance_after', 12, 2)->default(0);
                $table->string('note')->nullable();
                $table->char('period_key', 7)->index();                  // Y-m
                $table->timestamp('created_at')->nullable();
            });
        }

        // مطالبة (فاتورة تحصيل) تجمّع بطاقات غير مدفوعة لمندوب ضمن فترة
        if (! Schema::hasTable('sales_claims')) {
            Schema::create('sales_claims', function (Blueprint $table) {
                $table->id();
                $table->string('claim_code', 24)->unique();
                $table->unsignedBigInteger('user_id')->index();
                $table->date('date_from')->nullable();
                $table->date('date_to')->nullable();
                $table->unsignedInteger('invoices_count')->default(0);
                $table->decimal('invoices_total', 12, 2)->default(0);
                $table->enum('status', ['open', 'paid'])->default('open')->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // بنود المطالبة (بطاقة واحدة لكل بند)
        if (! Schema::hasTable('sales_claim_items')) {
            Schema::create('sales_claim_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('claim_id')->index();
                $table->unsignedBigInteger('card_id')->index();
                $table->decimal('amount', 12, 2)->default(0);
                $table->decimal('paid_amount', 12, 2)->default(0);
                $table->boolean('is_paid')->default(false)->index();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        // توزيع دفعة على بنود/بطاقات (FIFO)
        if (! Schema::hasTable('sales_payment_allocations')) {
            Schema::create('sales_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_tx_id')->index();   // sales_transactions.id
                $table->unsignedBigInteger('claim_id')->nullable()->index();
                $table->unsignedBigInteger('claim_item_id')->nullable()->index();
                $table->unsignedBigInteger('card_id')->nullable()->index();
                $table->decimal('amount', 12, 2)->default(0);
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_payment_allocations');
        Schema::dropIfExists('sales_claim_items');
        Schema::dropIfExists('sales_claims');
        Schema::dropIfExists('sales_transactions');
        Schema::dropIfExists('sales_limits');
        Schema::dropIfExists('sales_points');
        Schema::dropIfExists('doc_counters');
    }
};
