<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_flow_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            // Restrict, not cascade: deleting an account must not silently wipe manual entries.
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('entry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_flow_entries');
    }
};
