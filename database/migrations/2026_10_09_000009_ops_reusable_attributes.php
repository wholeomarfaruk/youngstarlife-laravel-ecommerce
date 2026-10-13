<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Global, reusable attributes (Shopify "options" / WooCommerce "global attributes"):
 *   ops_attributes        Color · Size · Material …   (type: color = swatches, size = ordered buttons, text = plain)
 *   ops_attribute_values  Blue #1F3A93 · M · Cotton … (defined once, picked on every product)
 *   ops_products.options          [{attribute_id, values: [value_id…]}]  → which options this product uses
 *   ops_product_variants.options   {"Color": "Blue", "Size": "M"}          → this variant's combination
 * variants.color / variants.size stay as denormalised copies (labels, scans, reports read them).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_attributes', function (Blueprint $t) {
            $t->string('slug', 60)->nullable()->after('name');
            $t->string('type', 10)->default('text')->after('slug');      // color | size | text
            $t->unsignedSmallInteger('sort')->default(0)->after('type');
            $t->boolean('is_filterable')->default(true)->after('sort');   // storefront filter
            $t->json('values')->nullable()->change();                     // legacy column, unused
        });
        Schema::create('ops_attribute_values', function (Blueprint $t) {
            $t->id(); $t->foreignId('attribute_id')->constrained('ops_attributes')->cascadeOnDelete();
            $t->string('value', 60); $t->string('slug', 80); $t->string('hex', 9)->nullable();   // swatch for colour attributes
            $t->unsignedSmallInteger('sort')->default(0); $t->timestamps();
            $t->unique(['attribute_id', 'value']);
        });
        Schema::table('ops_products', fn (Blueprint $t) => $t->json('options')->nullable());
        Schema::table('ops_product_variants', fn (Blueprint $t) => $t->json('options')->nullable()->after('size'));

        // ── backfill from the existing colour / size columns
        $now = now();
        $hex = ['black' => '#1A1A17', 'white' => '#FFFFFF', 'blue' => '#2C5FA8', 'navy' => '#1F2A44', 'olive' => '#6B7140', 'grey' => '#8A8A85', 'gray' => '#8A8A85',
            'yellow' => '#E8C547', 'red' => '#C0392B', 'green' => '#2E7D4F', 'pink' => '#E89AB0', 'brown' => '#7A5230', 'beige' => '#D9C7A7', 'maroon' => '#6E1E2B', 'orange' => '#E67E22', 'purple' => '#6C4BA0'];
        $sizeOrder = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', '4XL'];
        $colorId = DB::table('ops_attributes')->insertGetId(['name' => 'Color', 'slug' => 'color', 'type' => 'color', 'sort' => 1, 'is_filterable' => true, 'created_at' => $now, 'updated_at' => $now]);
        $sizeId = DB::table('ops_attributes')->insertGetId(['name' => 'Size', 'slug' => 'size', 'type' => 'size', 'sort' => 2, 'is_filterable' => true, 'created_at' => $now, 'updated_at' => $now]);
        $colors = DB::table('ops_product_variants')->whereNotNull('color')->where('color', '!=', '')->distinct()->orderBy('color')->pluck('color');
        $sizes = collect(DB::table('ops_product_variants')->whereNotNull('size')->where('size', '!=', '')->distinct()->pluck('size'))
            ->merge(['S', 'M', 'L', 'XL', 'XXL'])->unique()->sortBy(fn ($s) => ($i = array_search(strtoupper($s), $sizeOrder)) === false ? 100 : $i)->values();
        foreach ($colors as $i => $c) DB::table('ops_attribute_values')->insert(['attribute_id' => $colorId, 'value' => $c, 'slug' => str($c)->slug(), 'hex' => $hex[strtolower($c)] ?? null, 'sort' => $i, 'created_at' => $now, 'updated_at' => $now]);
        foreach ($sizes as $i => $s) DB::table('ops_attribute_values')->insert(['attribute_id' => $sizeId, 'value' => $s, 'slug' => str($s)->slug(), 'sort' => $i, 'created_at' => $now, 'updated_at' => $now]);

        $vals = DB::table('ops_attribute_values')->get()->groupBy('attribute_id')->map(fn ($g) => $g->pluck('id', 'value'));
        foreach (DB::table('ops_product_variants')->get(['id', 'product_id', 'color', 'size']) as $v) {
            $opt = array_filter(['Color' => $v->color ?: null, 'Size' => $v->size ?: null]);
            if ($opt) DB::table('ops_product_variants')->where('id', $v->id)->update(['options' => json_encode($opt)]);
        }
        foreach (DB::table('ops_product_variants')->get(['product_id', 'color', 'size'])->groupBy('product_id') as $pid => $vs) {
            $attrs = [];
            foreach ([[$colorId, 'color'], [$sizeId, 'size']] as [$aid, $col]) {
                $ids = $vs->pluck($col)->filter()->unique()->map(fn ($x) => $vals[$aid][$x] ?? null)->filter()->values()->all();
                if ($ids) $attrs[] = ['attribute_id' => $aid, 'values' => $ids];
            }
            if ($attrs) DB::table('ops_products')->where('id', $pid)->update(['options' => json_encode($attrs)]);
        }
    }

    public function down(): void
    {
        Schema::table('ops_product_variants', fn (Blueprint $t) => $t->dropColumn('options'));
        Schema::table('ops_products', fn (Blueprint $t) => $t->dropColumn('options'));
        Schema::dropIfExists('ops_attribute_values');
        DB::table('ops_attributes')->delete();
        Schema::table('ops_attributes', fn (Blueprint $t) => $t->dropColumn(['slug', 'type', 'sort', 'is_filterable']));
    }
};
