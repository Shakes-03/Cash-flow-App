<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pbe_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pbe_import_id')->constrained()->cascadeOnDelete();
            $table->string('employee_ref')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('client_name');
            $table->date('pay_date');
            $table->date('bank_date')->nullable();
            $table->unsignedInteger('week_no')->nullable();
            $table->unsignedInteger('month_no')->nullable();
            $table->decimal('gross_sales_invoice', 12, 2)->default(0);
            $table->decimal('net_sales_invoice', 12, 2)->default(0);
            $table->decimal('vat_sales_invoice', 12, 2)->default(0);
            $table->decimal('total_pay', 12, 2)->default(0);
            $table->decimal('ers_nic', 12, 2)->default(0);
            $table->decimal('ees_nic', 12, 2)->default(0);
            $table->decimal('ers_pension', 12, 2)->default(0);
            $table->decimal('ees_pension', 12, 2)->default(0);
            $table->decimal('apprenticeship_levy', 12, 2)->default(0);
            $table->decimal('margin', 12, 2)->default(0);
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['last_name', 'total_pay']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pbe_payments');
    }
};
