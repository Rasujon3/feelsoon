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
        Schema::create('musics', function (Blueprint $table) {
            $table->id(); // Primary key: id
            $table->unsignedBigInteger('user_id')->nullable(); // Optional user ID
            $table->string('title', 100); // Music title
            $table->string('file_path')->nullable(); // Path to the MP3 file
            $table->timestamps(); // created_at and updated_at

            // Foreign key constraint (if users table exists)
            # $table->foreign('user_id')->references('user_id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('musics');
    }
};
