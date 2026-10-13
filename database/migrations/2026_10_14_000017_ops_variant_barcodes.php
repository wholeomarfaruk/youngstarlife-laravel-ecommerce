<?php

use App\Support\Barcode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Every variant has a barcode from now on: the empty ones get a generated EAN-13 (supplier barcodes already entered stay). */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ops_product_variants')->where(fn ($q) => $q->whereNull('barcode')->orWhere('barcode', ''))->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $v) DB::table('ops_product_variants')->where('id', $v->id)->update(['barcode' => Barcode::forVariant($v->id)]);
            });
    }

    public function down(): void {}   // generated barcodes may already be printed on labels — keep them
};
