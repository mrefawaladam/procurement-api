<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sku', 'name', 'category', 'quantity', 'unit_price'])]
class Stock extends Model
{
    use SoftDeletes;

    protected $table = 'stock';

    public function requestItems(): HasMany
    {
        return $this->hasMany(RequestItem::class);
    }
}
