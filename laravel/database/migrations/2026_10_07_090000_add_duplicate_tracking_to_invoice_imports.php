<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Not unique: manually entered invoices may have no number, and existing data
            // may already hold repeats. Re-imports are de-duplicated in InvoiceImportService.
            $table->index('invoice_number');
        });

        Schema::table('invoice_imports', function (Blueprint $table) {
            $table->unsignedInteger('duplicates_skipped')->default(0)->after('total_rows');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['invoice_number']);
        });

        Schema::table('invoice_imports', function (Blueprint $table) {
            $table->dropColumn('duplicates_skipped');
        });
    }
};
