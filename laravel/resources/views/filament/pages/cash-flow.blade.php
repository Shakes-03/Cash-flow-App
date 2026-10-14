<x-filament-panels::page>
    @php
        $cashFlow = $this->getCashFlow();
        $weeks = $cashFlow['weeks'];
        $rows = $cashFlow['rows'];
        $totals = $cashFlow['totals'];

        $incomeRows = array_filter($rows, fn ($row) => $row['section'] === 'income');
        $costOfSalesRows = array_filter($rows, fn ($row) => $row['section'] === 'cost_of_sales');
        $expenseRows = array_filter($rows, fn ($row) => $row['section'] === 'expense');

        $money = fn ($value) => $value == 0 ? '—' : ($value < 0 ? '-£' : '£').number_format(abs($value), 2);

        $cellStyle = 'padding:6px 12px;text-align:right;white-space:nowrap;';
        $labelStyle = 'padding:6px 12px;text-align:left;';
        $borderStyle = 'border-top:1px solid rgba(127,127,127,0.25);';
        $headerStyle = 'padding:6px 12px;text-align:right;white-space:nowrap;font-weight:600;color:inherit;';
        $sectionStyle = 'padding:6px 12px;font-weight:600;color:inherit;background:rgba(127,127,127,0.08);';
    @endphp

    @if (empty($weeks))
        <x-filament::section>
            No transactions have been imported yet.
        </x-filament::section>
    @else
        @if (! empty($cashFlow['balanceChecks']))
            <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger">
                <x-slot name="heading">Closing balance doesn't agree with the bank</x-slot>
                <x-slot name="description">
                    In the weeks below, the report's closing balance (excluding manual entries) moved away from the bank's own balance.
                    Usually a duplicated or missing transaction, or a gap between imported statements, in that week. The difference carries into every later week.
                </x-slot>
                <table style="border-collapse:collapse;font-size:0.875rem;">
                    <thead>
                        <tr>
                            <th style="{{ $labelStyle }} font-weight:600;">Week</th>
                            <th style="{{ $headerStyle }}">Report closing</th>
                            <th style="{{ $headerStyle }}">Bank balance</th>
                            <th style="{{ $headerStyle }}">Change this week</th>
                            <th style="{{ $headerStyle }}">Total difference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cashFlow['balanceChecks'] as $check)
                            <tr>
                                <td style="{{ $labelStyle }} {{ $borderStyle }}">{{ $check['week'] }}</td>
                                <td style="{{ $cellStyle }} {{ $borderStyle }}">{{ $money($check['report']) }}</td>
                                <td style="{{ $cellStyle }} {{ $borderStyle }}">{{ $money($check['bank']) }}</td>
                                <td style="{{ $cellStyle }} {{ $borderStyle }} color:var(--danger-600, #dc2626);">{{ $money($check['change']) }}</td>
                                <td style="{{ $cellStyle }} {{ $borderStyle }}">{{ $money($check['difference']) }}</td>
                            </tr>
                            @foreach ($check['duplicates'] as $duplicate)
                                <tr>
                                    <td colspan="4" style="{{ $labelStyle }} opacity:0.8;">
                                        Likely duplicate: #{{ $duplicate->id }} {{ $duplicate->description }},
                                        {{ $money((float) $duplicate->amount) }} on {{ $duplicate->transaction_date->format('d M Y') }}
                                        (identical to an earlier row, same running balance)
                                    </td>
                                    <td style="{{ $cellStyle }}">{{ ($this->deleteDuplicateAction)(['id' => $duplicate->id]) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </x-filament::section>
        @endif

        <x-filament::section>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                    <thead>
                        <tr>
                            <th style="padding:6px 12px;text-align:left;font-weight:600;color:inherit;">Description</th>
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
                        <tr>
                            <td style="{{ $labelStyle }} {{ $borderStyle }}" title="Loans and inter-company funding (accounts marked Funding / loan)">Funding Capital</td>
                            @foreach ($weeks as $i => $week)
                                <td style="{{ $cellStyle }} {{ $borderStyle }}">{{ $money($totals['funding'][$i]) }}</td>
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
