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
        Schema::create('black_lists', function (Blueprint $table) {
            $table->id();
            $table->morphs('black_listable');
            $table->string('reason')->nullable();
            $table->timestamps();
            //relation morph cascade


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('black_lists');
    }
};
