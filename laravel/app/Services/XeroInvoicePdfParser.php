<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

class XeroInvoicePdfParser
{
    /**
     * Reads a Xero "Tax Invoice" PDF into an invoice row (same keys as the invoice CSV).
     * The amount is the invoice Total incl. VAT - not "Amount due", which is lower when
     * the PDF was saved after a part-payment.
     *
     * @return array{'Customer Name': string, 'Invoice Number': string, 'Invoice Date': string, 'Amount': string, 'Description': ?string}
     */
    public function parse(string $path): array
    {
        try {
            $text = (new Parser)->parseFile($path)->getText();
        } catch (\Throwable $e) {
            throw new \RuntimeException('the PDF could not be opened ('.$e->getMessage().').');
        }

        // Xero puts zero-width spaces in front of the dates and invoice number.
        $text = str_replace(["\u{200B}", "\u{FEFF}", "\r"], '', $text);

        if (! preg_match('/^\s*(Tax )?Invoice\b/i', $text)) {
            throw new \RuntimeException('this does not look like a Xero tax invoice (credit notes and statements are not imported).');
        }

        $customerName = $this->match('/^Bill to\s*\n\s*(.+?)\s*$/mu', $text);
        $invoiceNumber = $this->match('/^Invoice number\s*\n\s*(\S+)/mu', $text);
        $issueDate = $this->match('/^Issue date\s*\n\s*(.+?)\s*$/mu', $text);
        // "Total706.50" - but not "Total VAT 20% 117.75".
        $total = $this->match('/^Total\s*£?\s*(-?[\d,]+\.\d{2})\s*$/mu', $text);

        $missing = array_keys(array_filter([
            'customer' => $customerName,
            'invoice number' => $invoiceNumber,
            'issue date' => $issueDate,
            'total' => $total,
        ], fn ($value) => $value === null));

        if ($missing) {
            throw new \RuntimeException('could not find the '.implode(', ', $missing).' on this invoice.');
        }

        // Line items read "Rosemary Joshi: Standard rate11 21.00 ..." - keep the worker names.
        $workers = [];
        if (preg_match('/^Description.*?$(.*?)^Subtotal/ms', $text, $m)) {
            preg_match_all('/^([^:\n]+):/m', $m[1], $names);
            $workers = array_values(array_unique(array_map('trim', $names[1])));
        }

        return [
            'Customer Name' => $customerName,
            'Invoice Number' => $invoiceNumber,
            'Invoice Date' => $issueDate,
            'Amount' => $total,
            'Description' => $workers ? mb_substr(implode(', ', $workers), 0, 255) : null,
        ];
    }

    private function match(string $pattern, string $text): ?string
    {
        return preg_match($pattern, $text, $m) ? trim($m[1]) : null;
    }
}
