<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'customer_id',
        'invoice_import_id',
        'invoice_number',
        'invoice_date',
        'amount',
        'description',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoiceImport(): BelongsTo
    {
        return $this->belongsTo(InvoiceImport::class);
    }
}
