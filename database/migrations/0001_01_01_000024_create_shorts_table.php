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
        Schema::create('shorts', function (Blueprint $table) {
            $table->id();
            $table->uuid('project_id');
            $table->uuid('page_id');
            $table->uuid('element_id');
            $table->text('text');
            $table->unsignedInteger('search_count');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shorts');
    }
};