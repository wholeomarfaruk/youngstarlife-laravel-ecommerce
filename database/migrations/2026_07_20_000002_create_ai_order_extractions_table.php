<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_order_extractions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('source')->default('other');
            $table->string('input_type');
            $table->longText('raw_text_input')->nullable();
            $table->string('image_path')->nullable();
            $table->json('extracted_json');
            $table->decimal('confidence', 3, 2)->nullable();
            $table->json('warnings')->nullable();
            $table->json('resolved_json')->nullable();
            $table->string('status')->default('pending_review');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->timestamps();

            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_order_extractions');
    }
};
