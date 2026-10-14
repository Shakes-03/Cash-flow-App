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

    protected static function booted(): void
    {
        static::saving(function (Invoice $invoice) {
            $invoice->invoice_number_key = static::normaliseNumber($invoice->invoice_number);
        });
    }

    /**
     * Case, spaces and punctuation don't make a different invoice: "INV-0012", "inv 0012"
     * and "INV0012" all give "inv0012". Null when there is no number.
     */
    public static function normaliseNumber(?string $invoiceNumber): ?string
    {
        $key = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $invoiceNumber));

        return $key === '' ? null : $key;
    }

    /** Another invoice with the same number, ignoring case, spaces and punctuation. */
    public static function findByNumber(?string $invoiceNumber, ?int $exceptId = null): ?Invoice
    {
        $key = static::normaliseNumber($invoiceNumber);

        return $key === null ? null : static::query()
            ->where('invoice_number_key', $key)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->first();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoiceImport(): BelongsTo
    {
        return $this->belongsTo(InvoiceImport::class);
    }
}
