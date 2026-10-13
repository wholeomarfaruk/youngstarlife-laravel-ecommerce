<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Combo with a size per item (product page): the customer's chosen component variants travel cart line → order line,
 * and stock allocation / packing use them instead of the combo's default components.
 * combo_selection: [{"combo_item_id":1,"variant_id":12,"qty":1,"size":"32","color":"Beige","name":"6 Pocket Cargo"}, …]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $t) {
            $t->string('selection_key', 120)->default('')->after('variant_id');   // '' = no choice; "32|34" = combo sizes → its own line
            $t->json('combo_selection')->nullable()->after('selection_key');
            $t->index('cart_id', 'cart_items_cart_lookup');                       // keeps the cart_id foreign key indexed while the unique key changes
        });
        Schema::table('cart_items', function (Blueprint $t) {
            $t->dropUnique('cart_items_cart_id_variant_id_unique');
            $t->unique(['cart_id', 'variant_id', 'selection_key']);
        });
        Schema::table('ops_order_items', function (Blueprint $t) {
            $t->json('combo_selection')->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('ops_order_items', fn (Blueprint $t) => $t->dropColumn('combo_selection'));
        Schema::table('cart_items', function (Blueprint $t) {
            $t->dropUnique(['cart_id', 'variant_id', 'selection_key']);
            $t->unique(['cart_id', 'variant_id']);
            $t->dropIndex('cart_items_cart_lookup');
            $t->dropColumn(['selection_key', 'combo_selection']);
        });
    }
};
