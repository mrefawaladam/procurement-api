<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['request_id', 'vendor_id', 'po_number', 'status', 'total_cost'])]
class ProcurementOrder extends Model
{
    use SoftDeletes;

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
