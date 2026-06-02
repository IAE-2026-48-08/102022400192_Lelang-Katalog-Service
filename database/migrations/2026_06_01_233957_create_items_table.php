<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->bigInteger('starting_price');
            $table->bigInteger('current_highest_bid')->default(0);
            $table->enum('auction_status', ['OPEN', 'CLOSED', 'SOLD'])->default('OPEN');
            $table->timestamp('auction_deadline');
            $table->string('image_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};