<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Model;

/**
 * Base model for the new Ops admin panel. Every Ops table is prefixed with "ops_"
 * so it lives beside the current storefront tables (orders, products, customers…)
 * without colliding. Table names are otherwise Laravel's defaults (Order → ops_orders).
 */
abstract class OpsModel extends Model
{
    public function getTable()
    {
        return $this->table ??= 'ops_'.parent::getTable();
    }
}
