<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
        $ledger = $this->getLedger();
        $money = fn ($value) => '£'.number_format($value, 2);

        $thStyle = 'padding:6px 12px;text-align:left;font-weight:600;color:var(--gray-700, #374151);';
        $thRightStyle = 'padding:6px 12px;text-align:right;font-weight:600;color:var(--gray-700, #374151);';
        $tdStyle = 'padding:6px 12px;text-align:left;border-top:1px solid var(--gray-200, #e5e7eb);';
        $tdRightStyle = 'padding:6px 12px;text-align:right;border-top:1px solid var(--gray-200, #e5e7eb);white-space:nowrap;';
    @endphp

    <x-filament::section heading="Debtors Book">
        @if ($summary->isEmpty())
            <p style="color:var(--gray-500, #6b7280);">No customers yet.</p>
        @else
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                    <thead>
                        <tr>
                            <th style="{{ $thStyle }}">Customer</th>
                            <th style="{{ $thStyle }}">Customer #</th>
                            <th style="{{ $thRightStyle }}">Invoiced</th>
                            <th style="{{ $thRightStyle }}">Received</th>
                            <th style="{{ $thRightStyle }}">Balance</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary as $row)
                            <tr>
                                <td style="{{ $tdStyle }}">{{ $row->customer->name }}</td>
                                <td style="{{ $tdStyle }} color:var(--gray-500, #6b7280);">{{ $row->customer->customer_number }}</td>
                                <td style="{{ $tdRightStyle }}">{{ $money($row->invoiced) }}</td>
                                <td style="{{ $tdRightStyle }}">{{ $money($row->received) }}</td>
                                <td style="{{ $tdRightStyle }} font-weight:600; color: {{ $row->balance > 0 ? 'var(--danger-600, #dc2626)' : 'var(--success-600, #16a34a)' }};">{{ $money($row->balance) }}</td>
                                <td style="{{ $tdRightStyle }}">
                                    <button
                                        type="button"
                                        wire:click="$set('customerId', {{ $row->customer->id }})"
                                        style="color:var(--primary-600, #d97706);font-weight:500;background:none;border:none;cursor:pointer;text-decoration:underline;"
                                    >
                                        View ledger
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Customer Ledger">
        <div style="margin-bottom:1rem;max-width:24rem;">
            <select wire:model.live="customerId" style="display:block;width:100%;border-radius:0.5rem;border:1px solid var(--gray-300, #d1d5db);padding:0.5rem;font-size:0.875rem;background:var(--gray-50, #f9fafb);color:var(--gray-900, #111827);">
                <option value="">Select a customer…</option>
                @foreach ($this->getCustomerOptions() as $id => $name)
                    <option value="{{ $id }}" @selected($this->customerId == $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>

        @if ($ledger === null)
            <p style="color:var(--gray-500, #6b7280);">Select a customer above to see their invoice/payment ledger.</p>
        @elseif ($ledger->isEmpty())
            <p style="color:var(--gray-500, #6b7280);">No invoices or payments for this customer yet.</p>
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
                                <td style="{{ $tdStyle }}">{{ \Illuminate\Support\Carbon::parse($entry->date)->format('d M Y') }}</td>
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
    </x-filament::section>
</x-filament-panels::page>
