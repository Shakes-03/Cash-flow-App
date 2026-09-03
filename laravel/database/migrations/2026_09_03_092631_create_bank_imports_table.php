<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bank_imports', function (Blueprint $table) {
               $table->id();
               $table->string('filename');
               $table->timestamp('imported_at');
               $table->string('status')->default('completed');
               $table->integer('total_rows')->default(0);
               $table->foreignId('user_id')->constrained()->cascadeOnDelete();
               $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_imports');
    }
};
