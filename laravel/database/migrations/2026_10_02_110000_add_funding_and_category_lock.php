<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Loans / inter-company funding: shown on the Funding Capital line, not in Sales or Expenses.
            $table->boolean('is_funding')->default(false)->after('is_cost_of_sales');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Set when someone picks a category by hand, so re-running the keyword rules leaves it alone.
            $table->boolean('category_locked')->default(false)->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('is_funding'));
        Schema::table('transactions', fn (Blueprint $table) => $table->dropColumn('category_locked'));
    }
};
