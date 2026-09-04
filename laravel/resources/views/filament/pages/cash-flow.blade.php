<x-filament-panels::page>
    @php
        $cashFlow = $this->getCashFlow();
        $weeks = $cashFlow['weeks'];
        $rows = $cashFlow['rows'];
        $totals = $cashFlow['totals'];

        $incomeRows = array_filter($rows, fn ($row) => $row['section'] === 'income');
        $costOfSalesRows = array_filter($rows, fn ($row) => $row['section'] === 'cost_of_sales');
        $expenseRows = array_filter($rows, fn ($row) => $row['section'] === 'expense');

        $money = fn ($value) => $value == 0 ? '—' : '£'.number_format($value, 2);

        $cellStyle = 'padding:6px 12px;text-align:right;white-space:nowrap;';
        $labelStyle = 'padding:6px 12px;text-align:left;';
        $borderStyle = 'border-top:1px solid var(--gray-200, #e5e7eb);';
        $headerStyle = 'padding:6px 12px;text-align:right;white-space:nowrap;font-weight:600;color:var(--gray-700, #374151);';
        $sectionStyle = 'padding:6px 12px;font-weight:600;color:var(--gray-600, #4b5563);background:var(--gray-50, #f9fafb);';
    @endphp

    @if (empty($weeks))
        <x-filament::section>
            No transactions have been imported yet.
        </x-filament::section>
    @else
        <x-filament::section>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                    <thead>
                        <tr>
                            <th style="padding:6px 12px;text-align:left;font-weight:600;color:var(--gray-700, #374151);">Description</th>
                            @foreach ($weeks as $week)
                                <th style="{{ $headerStyle }}">{{ $week['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="{{ count($weeks) + 1 }}" style="{{ $sectionStyle }}">Sales Revenue</td>
                        </tr>
                        @foreach ($incomeRows as $row)
                            <tr>
                                <td style="{{ $labelStyle }} {{ $borderStyle }}">{{ $row['label'] }}</td>
                                @foreach ($weeks as $i => $week)
                                    <td style="{{ $cellStyle }} {{ $borderStyle }}">{{ $money($row['values'][$i] ?? 0) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr style="font-weight:600;">
                            <td style="{{ $labelStyle }} border-top:2px solid var(--gray-300, #d1d5db);">Total Sales</td>
                            @foreach ($weeks as $i => $week)
                                <td style="{{ $cellStyle }} border-top:2px solid var(--gray-300, #d1d5db);">{{ $money($totals['income'][$i]) }}</td>
                            @endforeach
                        </tr>

                        <tr>
                            <td colspan="{{ count($weeks) + 1 }}" style="{{ $sectionStyle }}">Cost of Sales</td>
                        </tr>
                        @foreach ($costOfSalesRows as $row)
                            <tr>
                                <td style="{{ $labelStyle }} {{ $borderStyle }}">{{ $row['label'] }}</td>
                                @foreach ($weeks as $i => $week)
                                    <td style="{{ $cellStyle }} {{ $borderStyle }}">{{ $money($row['values'][$i] ?? 0) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr style="font-weight:600;">
                            <td style="{{ $labelStyle }} border-top:2px solid var(--gray-300, #d1d5db);">Total Cost of Sales</td>
                            @foreach ($weeks as $i => $week)
                                <td style="{{ $cellStyle }} border-top:2px solid var(--gray-300, #d1d5db);">{{ $money($totals['costOfSales'][$i]) }}</td>
                            @endforeach
                        </tr>

                        <tr style="font-weight:600;">
                            <td style="{{ $labelStyle }} border-top:2px solid var(--gray-400, #9ca3af);">Gross Profit</td>
                            @foreach ($weeks as $i => $week)
                                <td style="{{ $cellStyle }} border-top:2px solid var(--gray-400, #9ca3af);">{{ $money($totals['gross'][$i]) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td style="{{ $labelStyle }} color:var(--gray-500, #6b7280);">Gross Margin %</td>
                            @foreach ($weeks as $i => $week)
                                <td style="{{ $cellStyle }} color:var(--gray-500, #6b7280);">{{ $totals['income'][$i] > 0 ? number_format($totals['grossMargin'][$i] * 100, 1).'%' : '—' }}</td>
                            @endforeach
                        </tr>

                        <tr>
                            <td colspan="{{ count($weeks) + 1 }}" style="{{ $sectionStyle }}">Expenses</td>
                        </tr>
                        @foreach ($expenseRows as $row)
                            <tr>
                                <td style="{{ $labelStyle }} {{ $borderStyle }}">{{ $row['label'] }}</td>
                                @foreach ($weeks as $i => $week)
                                    <td style="{{ $cellStyle }} {{ $borderStyle }}">{{ $money($row['values'][$i] ?? 0) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr style="font-weight:600;">
                            <td style="{{ $labelStyle }} border-top:2px solid var(--gray-300, #d1d5db);">Total Expenses</td>
                            @foreach ($weeks as $i => $week)
                                <td style="{{ $cellStyle }} border-top:2px solid var(--gray-300, #d1d5db);">{{ $money($totals['expenses'][$i]) }}</td>
                            @endforeach
                        </tr>

                        <tr style="font-weight:600;">
                            <td style="{{ $labelStyle }} border-top:2px solid var(--gray-400, #9ca3af);">Net Cashflow</td>
                            @foreach ($weeks as $i => $week)
                                <td style="{{ $cellStyle }} border-top:2px solid var(--gray-400, #9ca3af); color: {{ $totals['net'][$i] < 0 ? 'var(--danger-600, #dc2626)' : 'var(--success-600, #16a34a)' }};">{{ $money($totals['net'][$i]) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td style="{{ $labelStyle }} {{ $borderStyle }}">Opening Balance</td>
                            @foreach ($weeks as $i => $week)
                                <td style="{{ $cellStyle }} {{ $borderStyle }}">{{ $money($totals['opening'][$i]) }}</td>
                            @endforeach
                        </tr>
                        <tr style="font-weight:600;">
                            <td style="{{ $labelStyle }} {{ $borderStyle }}">Closing Balance</td>
                            @foreach ($weeks as $i => $week)
                                <td style="{{ $cellStyle }} {{ $borderStyle }}">{{ $money($totals['closing'][$i]) }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
