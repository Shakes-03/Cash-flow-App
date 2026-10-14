<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PbePayment extends Model
{
    protected $fillable = [
        'pbe_import_id',
        'employee_ref',
        'first_name',
        'last_name',
        'client_name',
        'pay_date',
        'bank_date',
        'week_no',
        'month_no',
        'gross_sales_invoice',
        'net_sales_invoice',
        'vat_sales_invoice',
        'total_pay',
        'ers_nic',
        'ees_nic',
        'ers_pension',
        'ees_pension',
        'apprenticeship_levy',
        'margin',
        'transaction_id',
    ];

    protected $casts = [
        'pay_date' => 'date',
        'bank_date' => 'date',
        'gross_sales_invoice' => 'decimal:2',
        'net_sales_invoice' => 'decimal:2',
        'vat_sales_invoice' => 'decimal:2',
        'total_pay' => 'decimal:2',
        'ers_nic' => 'decimal:2',
        'ees_nic' => 'decimal:2',
        'ers_pension' => 'decimal:2',
        'ees_pension' => 'decimal:2',
        'apprenticeship_levy' => 'decimal:2',
        'margin' => 'decimal:2',
    ];

    public function pbeImport(): BelongsTo
    {
        return $this->belongsTo(PbeImport::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
