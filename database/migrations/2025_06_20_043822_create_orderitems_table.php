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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id('tid');

            // Corrected foreign key for orders.oid
            $table->unsignedBigInteger('order_id');
            $table->foreign('order_id')->references('oid')->on('orders')->onDelete('cascade');

            // Corrected foreign key for products.pid
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('pid')->on('products')->onDelete('cascade');

            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2); // price per item
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orderitems');
    }
};
