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
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->uuid('project_id');
            $table->uuid('parent')->nullable();
            $table->uuid('name');
            $table->uuid('slug');
            $table->string('path');
            $table->string('type');
            $table->string('method');
            $table->boolean('public')->default(false);
            $table->boolean('ssr')->default(false);
            $table->boolean('email_verify')->default(false);
            $table->boolean('subview')->default(false);
            $table->json('data');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};