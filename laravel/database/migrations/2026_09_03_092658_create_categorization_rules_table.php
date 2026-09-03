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
        Schema::create('categorization_rules', function (Blueprint $table) {
            $table->id();
            $table->string('keyword');
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->enum('match_type', ['exact', 'contains'])->default('contains');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categorization_rules');
    }
};
