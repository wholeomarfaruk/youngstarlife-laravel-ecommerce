<?php

namespace App\Livewire\Ops\Concerns;

use App\Models\Ops\{Attribute, Batch, Brand, Category, Product, ProductVariant, StockMovement};
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

/** KPI strip + sub-tab counts shared by Products (all views) and Attributes. */
trait InventoryStats
{
    /** variants below their product's low-stock threshold (includes out of stock) */
    public static function lowQuery()
    {
        return ProductVariant::query()->join('ops_products as lp', 'lp.id', '=', 'ops_product_variants.product_id')->whereNull('lp.deleted_at')
            ->where('ops_product_variants.is_active', true)->whereColumn('ops_product_variants.stock', '<', 'lp.low_stock_threshold');
    }

    #[Computed]
    public function inv(): array
    {
        return cache()->remember('ops.inv.stats', 30, function () {
            $v = ProductVariant::whereHas('product')->selectRaw('COUNT(*) n, COALESCE(SUM(stock),0) stock, COALESCE(SUM(reserved),0) reserved, COALESCE(SUM(damaged),0) damaged, SUM(stock <= 0) out_n')->first();
            $low = static::lowQuery()->count();
            return [
                'products' => Product::count(), 'drafts' => Product::where('status', 'draft')->count(),
                'variants' => (int) $v->n, 'stock' => (int) $v->stock, 'reserved' => (int) $v->reserved, 'damaged' => (int) $v->damaged,
                'value' => (int) Batch::where('remaining', '>', 0)->sum(DB::raw('remaining * unit_cost')),
                'low' => $low - (int) $v->out_n, 'out' => (int) $v->out_n, 'low_tab' => $low,
                'movements' => StockMovement::count(),
                'damaged_tab' => ProductVariant::whereHas('product')->where(fn ($q) => $q->where('damaged', '>', 0)->orWhereIn('id', StockMovement::where('type', 'return_restock')->select('variant_id')))->count(),
                'categories' => Category::count(), 'attributes' => Attribute::count(), 'brands' => Brand::count(),
            ];
        });
    }

    public static function flushInv(): void { cache()->forget('ops.inv.stats'); }
}
