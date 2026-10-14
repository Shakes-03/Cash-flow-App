<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
        $customer = $this->getSelectedCustomer();
        $ledger = $this->getLedger();
        $allocation = $this->getAllocation();
        $money = fn ($value) => ($value < 0 ? '-' : '').'£'.number_format(abs($value), 2);
        $moneyOrDash = fn ($value) => abs($value) >= 0.005 ? $money($value) : '—';

        $thStyle = 'padding:6px 12px;text-align:left;font-weight:600;color:var(--gray-700, #374151);';
        $thRightStyle = 'padding:6px 12px;text-align:right;font-weight:600;color:var(--gray-700, #374151);white-space:nowrap;';
        $tdStyle = 'padding:6px 12px;text-align:left;border-top:1px solid var(--gray-200, #e5e7eb);';
        $tdRightStyle = 'padding:6px 12px;text-align:right;border-top:1px solid var(--gray-200, #e5e7eb);white-space:nowrap;';
        $mutedStyle = 'color:var(--gray-500, #6b7280);';
        $statusColor = ['paid' => 'success', 'part paid' => 'warning', 'unpaid' => 'danger'];
    @endphp

    <x-filament::section heading="Debtors Book" description="Balance = opening balance + invoices − payments received. Outstanding amounts are aged from the invoice date, with payments settling the oldest invoices first.">
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:0.75rem;margin-bottom:1rem;">
            <label for="as-at" style="font-weight:600;font-size:0.875rem;">As at</label>
            <div style="width:12rem;">
                <x-filament::input.wrapper prefix-icon="heroicon-o-calendar-days">
                    <x-filament::input.select id="as-at" wire:model.live="asAtChoice">
                        <option value="">Today</option>
                        @foreach ($this->getAsAtOptions() as $date => $label)
                            <option value="{{ $date }}">{{ $label }}</option>
                        @endforeach
                        <option value="custom">Custom date…</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            @if ($this->asAtChoice === 'custom')
                <div style="width:11rem;">
                    <x-filament::input.wrapper>
                        <x-filament::input type="date" wire:model.live="asAt" />
                    </x-filament::input.wrapper>
                </div>
            @endif
            <span style="{{ $mutedStyle }} font-size:0.8rem;">To check against Xero, pick the last month-end Xero has been fully booked to and run Xero's Aged Receivables for the same date.</span>
        </div>
        @php $grandTotal = $summary->sum('balance'); @endphp
        @if ($summary->isEmpty())
            <p style="{{ $mutedStyle }}">No customers yet.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                    <thead>
                        <tr>
                            <th style="{{ $thStyle }}">Customer</th>
                            <th style="{{ $thRightStyle }}">Opening</th>
                            <th style="{{ $thRightStyle }}">Invoiced</th>
                            <th style="{{ $thRightStyle }}">Received</th>
                            <th style="{{ $thRightStyle }}">Balance</th>
                            <th style="{{ $thRightStyle }}">0–30 days</th>
                            <th style="{{ $thRightStyle }}">31–60</th>
                            <th style="{{ $thRightStyle }}">61–90</th>
                            <th style="{{ $thRightStyle }}">90+</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary as $row)
                            <tr>
                                <td style="{{ $tdStyle }}">
                                    {{ $row->customer->name }}
                                    @if ($row->customer->opening_balance_date)
                                        <div style="{{ $mutedStyle }} font-size:0.75rem;">from {{ $row->customer->opening_balance_date->format('d M Y') }}</div>
                                    @endif
                                </td>
                                <td style="{{ $tdRightStyle }}">{{ $moneyOrDash($row->opening) }}</td>
                                <td style="{{ $tdRightStyle }}">{{ $moneyOrDash($row->invoiced) }}</td>
                                <td style="{{ $tdRightStyle }}">{{ $moneyOrDash($row->received) }}</td>
                                <td style="{{ $tdRightStyle }} font-weight:600; color: {{ $row->balance > 0 ? 'var(--danger-600, #dc2626)' : 'var(--success-600, #16a34a)' }};">{{ $money($row->balance) }}</td>
                                <td style="{{ $tdRightStyle }}">{{ $moneyOrDash($row->ageing['current']) }}</td>
                                <td style="{{ $tdRightStyle }}">{{ $moneyOrDash($row->ageing['31_60']) }}</td>
                                <td style="{{ $tdRightStyle }}">{{ $moneyOrDash($row->ageing['61_90']) }}</td>
                                <td style="{{ $tdRightStyle }} {{ $row->ageing['over_90'] > 0 ? 'color:var(--danger-600, #dc2626);font-weight:600;' : '' }}">{{ $moneyOrDash($row->ageing['over_90']) }}</td>
                                <td style="{{ $tdRightStyle }}">
                                    <button
                                        type="button"
                                        wire:click="$set('customerId', {{ $row->customer->id }})"
                                        style="color:var(--primary-600, #d97706);font-weight:500;background:none;border:none;cursor:pointer;text-decoration:underline;"
                                    >
                                        View
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td style="{{ $tdStyle }} font-weight:700;">Total</td>
                            <td style="{{ $tdRightStyle }} font-weight:700;">{{ $moneyOrDash($summary->sum('opening')) }}</td>
                            <td style="{{ $tdRightStyle }} font-weight:700;">{{ $moneyOrDash($summary->sum('invoiced')) }}</td>
                            <td style="{{ $tdRightStyle }} font-weight:700;">{{ $moneyOrDash($summary->sum('received')) }}</td>
                            <td style="{{ $tdRightStyle }} font-weight:700;">{{ $money($grandTotal) }}</td>
                            @foreach (['current', '31_60', '61_90', 'over_90'] as $bucket)
                                <td style="{{ $tdRightStyle }} font-weight:700;">{{ $moneyOrDash($summary->sum(fn ($r) => $r->ageing[$bucket])) }}</td>
                            @endforeach
                            <td style="{{ $tdRightStyle }}"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Customer Detail">
        <div style="margin-bottom:1rem;max-width:24rem;">
            <select wire:model.live="customerId" style="display:block;width:100%;border-radius:0.5rem;border:1px solid var(--gray-300, #d1d5db);padding:0.5rem;font-size:0.875rem;background:var(--gray-50, #f9fafb);color:var(--gray-900, #111827);">
                <option value="">Select a customer…</option>
                @foreach ($this->getCustomerOptions() as $id => $name)
                    <option value="{{ $id }}" @selected($this->customerId == $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>

        @if (! $customer)
            <p style="{{ $mutedStyle }}">Select a customer above to see which invoices are outstanding and their full ledger.</p>
        @else
            <h3 style="font-weight:600;margin:0 0 0.5rem;">Invoices &amp; what's been paid</h3>
            @if ($allocation->items->isEmpty())
                <p style="{{ $mutedStyle }} margin-bottom:1.5rem;">No invoices for this customer{{ $customer->opening_balance_date ? ' after '.$customer->opening_balance_date->format('d M Y') : '' }} - import them under Debtors → Invoice Imports.</p>
            @else
                <div style="overflow-x:auto;margin-bottom:0.5rem;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                        <thead>
                            <tr>
                                <th style="{{ $thStyle }}">Date</th>
                                <th style="{{ $thStyle }}">Invoice</th>
                                <th style="{{ $thRightStyle }}">Amount</th>
                                <th style="{{ $thRightStyle }}">Paid</th>
                                <th style="{{ $thRightStyle }}">Outstanding</th>
                                <th style="{{ $thRightStyle }}">Age (days)</th>
                                <th style="{{ $thStyle }}">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($allocation->items as $item)
                                <tr>
                                    <td style="{{ $tdStyle }}">{{ $item->date ? \Illuminate\Support\Carbon::parse($item->date)->format('d M Y') : '—' }}</td>
                                    <td style="{{ $tdStyle }}">
                                        {{ $item->label }}
                                        @if ($item->description)
                                            <span style="{{ $mutedStyle }}">— {{ $item->description }}</span>
                                        @endif
                                    </td>
                                    <td style="{{ $tdRightStyle }}">{{ $money($item->amount) }}</td>
                                    <td style="{{ $tdRightStyle }}">{{ $moneyOrDash($item->paid) }}</td>
                                    <td style="{{ $tdRightStyle }} font-weight:600;">{{ $moneyOrDash($item->outstanding) }}</td>
                                    <td style="{{ $tdRightStyle }}">{{ $item->outstanding > 0 ? $item->age_days : '—' }}</td>
                                    <td style="{{ $tdStyle }}"><x-filament::badge :color="$statusColor[$item->status]">{{ ucfirst($item->status) }}</x-filament::badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            @if ($allocation->unallocated > 0)
                <p style="color:var(--success-600, #16a34a);margin-bottom:1.5rem;">{{ $money($allocation->unallocated) }} received more than has been invoiced - the customer is in credit (or some invoices haven't been imported yet).</p>
            @else
                <div style="margin-bottom:1.5rem;"></div>
            @endif

            <h3 style="font-weight:600;margin:0 0 0.5rem;">Ledger</h3>
            @if ($ledger->isEmpty())
                <p style="{{ $mutedStyle }}">No invoices or payments for this customer yet.</p>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                        <thead>
                            <tr>
                                <th style="{{ $thStyle }}">Date</th>
                                <th style="{{ $thStyle }}">Description</th>
                                <th style="{{ $thRightStyle }}">Debit</th>
                                <th style="{{ $thRightStyle }}">Credit</th>
                                <th style="{{ $thRightStyle }}">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ledger as $entry)
                                <tr>
                                    <td style="{{ $tdStyle }}">{{ $entry->date ? \Illuminate\Support\Carbon::parse($entry->date)->format('d M Y') : '—' }}</td>
                                    <td style="{{ $tdStyle }}">{{ $entry->description }}</td>
                                    <td style="{{ $tdRightStyle }}">{{ $entry->debit > 0 ? $money($entry->debit) : '—' }}</td>
                                    <td style="{{ $tdRightStyle }}">{{ $entry->credit > 0 ? $money($entry->credit) : '—' }}</td>
                                    <td style="{{ $tdRightStyle }} font-weight:600;">{{ $money($entry->balance) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </x-filament::section>
</x-filament-panels::page>
