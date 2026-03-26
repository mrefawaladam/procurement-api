<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'contact_info', 'address'])]
class Vendor extends Model
{
    use SoftDeletes;

    public function procurementOrders(): HasMany
    {
        return $this->hasMany(ProcurementOrder::class);
    }
}
