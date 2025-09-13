<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('media_id'); // Foreign key to media_files table
            $table->string('ratio');
            $table->bigInteger('size');

            $table->string('path'); // Path to the variant file
            $table->timestamps(); // Created and updated timestamps

            // Foreign key constraint
            $table->foreign('media_id')->references('id')->on('media')->onDelete('cascade');
            $table->index(['ratio', 'media_id']);


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variants');
    }
};
