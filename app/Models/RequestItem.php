<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['request_id', 'stock_id', 'qty_requested', 'snapshot_price', 'subtotal'])]
class RequestItem extends Model
{
    public $timestamps = false;

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
