<?php

use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->date('opening_balance_date')->nullable();
        });

        // Normalised invoice number ("INV-0012", "inv 0012" and "INV0012 " are the same invoice),
        // enforced unique by the database so no import or manual entry can double an invoice.
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_number_key')->nullable();
        });

        $seen = [];
        foreach (DB::table('invoices')->whereNotNull('invoice_number')->orderBy('id')->get(['id', 'invoice_number']) as $invoice) {
            $key = Invoice::normaliseNumber($invoice->invoice_number);
            if ($key === null || isset($seen[$key])) {
                continue; // an existing duplicate keeps a null key; it shows under the "Possible duplicates" filter
            }
            $seen[$key] = true;
            DB::table('invoices')->where('id', $invoice->id)->update(['invoice_number_key' => $key]);
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique('invoice_number_key');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['invoice_number_key']);
            $table->dropColumn('invoice_number_key');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['opening_balance', 'opening_balance_date']);
        });
    }
};
