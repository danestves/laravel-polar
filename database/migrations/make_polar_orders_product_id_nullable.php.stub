<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Polar allows an order to have no product — `product_id` is nullable on their side — but
     * this table declared the column NOT NULL, so such an order would fail to sync with a
     * constraint violation instead of being recorded.
     *
     * There is deliberately no `down()`. Restoring the NOT NULL constraint would mean deleting
     * any order recorded without a product, and losing billing records is never the right answer
     * to a rollback. Widening a column is safe to leave in place.
     */
    public function up(): void
    {
        Schema::table('polar_orders', function (Blueprint $table) {
            $table->string('product_id')->nullable()->change();
        });
    }
};
