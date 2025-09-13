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
        Schema::create('media', function (Blueprint $table) {
            $table->id();
              $table->string('filename')->nullable();       // stored filename (e.g., abc123.jpg)
            $table->string('original_name')->nullable();  // original file name (e.g., photo.jpg)
            $table->string('mime_type')->nullable();      // file mime type (e.g., image/jpeg)
            $table->string('extension')->nullable();      // file extension (e.g., jpg)
            $table->bigInteger('size');       // file size in bytes
            $table->enum('type', [
                'image',
                'video',
                'audio',
                'document',
                'archive',
                'other'
            ])->nullable();
            $table->string('category')->nullable();
            //image/video/document
            $table->string('disk')->default('public'); // disk/storage location (optional)
            $table->string('path')->nullable();            // relative path or URL
            $table->timestamps(); // Created and updated timestamps
            $table->nullableMorphs('mediable'); // adds mediable_id and mediable_type columns
            $table->string('caption')->nullable();
            $table->json(column: 'json')->nullable();

            $table->unsignedBigInteger('user_id')->nullable(); // must allow NULL
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            // Indexing for better performance in queries involving model type and model ID
            $table->index(['user_id', 'type', 'category', 'created_at']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
