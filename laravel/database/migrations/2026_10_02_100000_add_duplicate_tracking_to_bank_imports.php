<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // The bank's own transaction ID (Revolut "ID" column). Not unique: rows imported
            // before this column existed have none, and are de-duplicated by fingerprint instead.
            $table->string('external_id')->nullable()->after('bank_import_id')->index();
        });

        Schema::table('bank_imports', function (Blueprint $table) {
            $table->unsignedInteger('duplicates_skipped')->default(0)->after('total_rows');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
            $table->dropColumn('external_id');
        });

        Schema::table('bank_imports', function (Blueprint $table) {
            $table->dropColumn('duplicates_skipped');
        });
    }
};
