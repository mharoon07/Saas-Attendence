<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary Slip - {{ $payroll->employee->name }}</title>
    <style>
        @page {
            margin: 20px 25px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #000000;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }

        .main-header {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .header-line {
            border-bottom: 2px solid #000;
            margin-bottom: 6px;
        }
        .top-timestamp {
            text-align: right;
            font-size: 10px;
            color: #333;
            margin-bottom: 8px;
        }

        /* Outer Box */
        .slip-box {
            border: 1px solid #000;
            padding: 10px;
        }

        /* Company Header Table */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .header-table td {
            vertical-align: top;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
        }
        .slip-title {
            font-size: 12px;
            font-weight: bold;
            margin-top: 2px;
        }
        .shift-badge {
            font-size: 10px;
            font-weight: bold;
            text-align: right;
            padding: 3px 8px;
            border: 1px solid #000;
            display: inline-block;
        }

        /* Info Grid Table */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            margin-bottom: 10px;
        }
        .info-table td {
            padding: 3px 5px;
            font-size: 10px;
            vertical-align: top;
        }
        .info-label {
            font-weight: normal;
            color: #333;
            width: 18%;
        }
        .info-value {
            font-weight: bold;
            width: 32%;
        }

        /* Main Content Table (Allowances vs Deductions) */
        .content-table {
            width: 100%;
            border-collapse: collapse;
        }
        .content-table td {
            vertical-align: top;
            padding: 0;
        }
        .section-title {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 6px;
            padding-bottom: 2px;
            border-bottom: 1px solid #ddd;
        }
        
        .left-column {
            width: 48%;
            border-right: 1px solid #000;
            padding-right: 10px;
        }
        .right-column {
            width: 52%;
            padding-left: 10px;
        }

        /* Key-Value Tables */
        .kv-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .kv-table td {
            padding: 3px 2px;
            vertical-align: top;
        }
        .kv-label {
            text-align: left;
        }
        .kv-value {
            text-align: right;
            font-weight: bold;
        }
        .no-data {
            font-size: 10px;
            color: #666;
            font-style: italic;
            padding: 4px 0;
        }

        /* Bottom Summary Bar */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            margin-top: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .summary-table td {
            padding: 5px 8px;
        }
    </style>
</head>
<body>

    <div class="main-header">Salary Slip For The Month of {{ strtoupper(\Carbon\Carbon::parse($payroll->period_start)->format('F-Y')) }}</div>
    <div class="header-line"></div>
    <div class="top-timestamp">{{ \Carbon\Carbon::now()->format('D, d M Y \a\t h:i a') }}</div>

    <div class="slip-box">
        <!-- Header: Company & Shift Info -->
        <table class="header-table">
            <tr>
                <td style="width: 70%;">
                     <div class="slip-title">
                        Pay Slip For the Month: {{ strtoupper(\Carbon\Carbon::parse($payroll->period_start)->format('F-Y')) }}
                    </div>
                </td>
                <td style="width: 30%; text-align: right;">
                    @php
                        $activeShiftName = $payroll->employee->employeeShifts->where('end_date', null)->first()?->shift?->name;
                    @endphp
                    @if($activeShiftName)
                        <div class="shift-badge">
                            Shift: {{ $activeShiftName }}
                        </div>
                    @endif
                </td>
            </tr>
        </table>

        <!-- Info Grid (Only display fields that have values) -->
        <table class="info-table">
            <tr>
                <td class="info-label">Sr. No:</td>
                <td class="info-value">#{{ $payroll->id }}</td>
                <td class="info-label">Emp Code:</td>
                <td class="info-value">{{ $payroll->employee->employee_code }}</td>
            </tr>
            <tr>
                <td class="info-label">Name:</td>
                <td class="info-value">{{ strtoupper($payroll->employee->name) }}</td>
                @php
                    $positionName = $payroll->employee->employeePositions->where('end_date', null)->first()?->position?->name;
                @endphp
                <td class="info-label">Designation:</td>
                <td class="info-value">{{ $positionName ? strtoupper($positionName) : 'N/A' }}</td>
            </tr>
            <tr>
                @if($payroll->employee->department_name)
                    <td class="info-label">Department:</td>
                    <td class="info-value">{{ strtoupper($payroll->employee->department_name) }}</td>
                @else
                    <td class="info-label">Joining Date:</td>
                    <td class="info-value">{{ $payroll->employee->hired_on ? \Carbon\Carbon::parse($payroll->employee->hired_on)->format('d-M-Y') : 'N/A' }}</td>
                @endif

                @if($payroll->employee->branch_name)
                    <td class="info-label">Branch:</td>
                    <td class="info-value">{{ strtoupper($payroll->employee->branch_name) }}</td>
                @else
                    <td class="info-label">Work Days:</td>
                    <td class="info-value">{{ $payroll->regular_working_days ?? 30 }} Days</td>
                @endif
            </tr>
            <tr>
                <td class="info-label">Salary Rate:</td>
                <td class="info-value">{{ $payroll->currency }} {{ number_format($payroll->base) }}</td>
                <td class="info-label">Work Days:</td>
                <td class="info-value">{{ $payroll->regular_working_days ?? 30 }} Days</td>
            </tr>
        </table>

        <!-- Main Content Table: Allowances vs Deductions -->
        <table class="content-table">
            <tr>
                <!-- Left Side: ALLOWANCES -->
                <td class="left-column">
                    <div class="section-title">ALLOWANCES</div>
                    <table class="kv-table">
                        <tr>
                            <td class="kv-label">Basic Pay:</td>
                            <td class="kv-value">{{ $payroll->currency }} {{ number_format($payroll->base) }}</td>
                        </tr>
                        @if(($payroll->performance_multiplier ?? 1) > 1)
                            <tr>
                                <td class="kv-label">Performance Bonus (x{{ $payroll->performance_multiplier }}):</td>
                                <td class="kv-value">{{ $payroll->currency }} {{ number_format(($payroll->base * $payroll->performance_multiplier) - $payroll->base) }}</td>
                            </tr>
                        @endif

                        @if($payroll->additions && $payroll->additions->overtime > 0)
                            <tr>
                                <td class="kv-label">Overtime Pay:</td>
                                <td class="kv-value">{{ $payroll->currency }} {{ number_format($payroll->additions->overtime) }}</td>
                            </tr>
                        @endif

                        @if($payroll->additions && is_array($payroll->additions->custom_items))
                            @foreach($payroll->additions->custom_items as $item)
                                @if(($item['amount'] ?? 0) > 0)
                                    <tr>
                                        <td class="kv-label">{{ $item['name'] ?? 'Custom Allowance' }}:</td>
                                        <td class="kv-value">{{ $payroll->currency }} {{ number_format($item['amount']) }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        @endif

                        <tr style="border-top: 1px solid #ddd;">
                            <td class="kv-label font-bold">Total Allowances:</td>
                            <td class="kv-value font-bold">{{ $payroll->currency }} {{ number_format($payroll->gross_salary) }}</td>
                        </tr>
                    </table>
                </td>

                <!-- Right Side: DEDUCTIONS (Only Available Items) -->
                <td class="right-column">
                    <div class="section-title">DEDUCTIONS</div>
                    <table class="kv-table">
                        @php
                            $hasDeductions = false;
                        @endphp

                        @if($payroll->deductions && $payroll->deductions->income_tax > 0)
                            @php $hasDeductions = true; @endphp
                            <tr>
                                <td class="kv-label">Income Tax:</td>
                                <td class="kv-value">{{ $payroll->currency }} {{ number_format($payroll->deductions->income_tax) }}</td>
                            </tr>
                        @endif

                        @if($payroll->deductions && $payroll->deductions->advance_payment_deduction > 0)
                            @php $hasDeductions = true; @endphp
                            <tr>
                                <td class="kv-label">Advance Salary:</td>
                                <td class="kv-value">{{ $payroll->currency }} {{ number_format($payroll->deductions->advance_payment_deduction) }}</td>
                            </tr>
                        @endif

                        @if($payroll->deductions && $payroll->deductions->undertime > 0)
                            @php $hasDeductions = true; @endphp
                            <tr>
                                <td class="kv-label">Fine / Undertime:</td>
                                <td class="kv-value">{{ $payroll->currency }} {{ number_format($payroll->deductions->undertime) }}</td>
                            </tr>
                        @endif

                        @if($payroll->deductions && $payroll->deductions->loan_deduction > 0)
                            @php $hasDeductions = true; @endphp
                            <tr>
                                <td class="kv-label">Loan Deduction:</td>
                                <td class="kv-value">{{ $payroll->currency }} {{ number_format($payroll->deductions->loan_deduction) }}</td>
                            </tr>
                        @endif

                        @if($payroll->deductions && is_array($payroll->deductions->custom_items))
                            @foreach($payroll->deductions->custom_items as $ci)
                                @if(($ci['amount'] ?? 0) > 0)
                                    @php $hasDeductions = true; @endphp
                                    <tr>
                                        <td class="kv-label">{{ $ci['name'] ?? 'Other Deduction' }}:</td>
                                        <td class="kv-value">{{ $payroll->currency }} {{ number_format($ci['amount']) }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        @endif

                        @if(!$hasDeductions)
                            <tr>
                                <td colspan="2" class="no-data">No deductions applied for this month.</td>
                            </tr>
                        @endif

                        <tr style="border-top: 1px solid #ddd;">
                            <td class="kv-label font-bold">Total Deductions:</td>
                            <td class="kv-value font-bold">{{ $payroll->currency }} {{ number_format($payroll->total_deductions) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Summary Totals Footer -->
        <table class="summary-table">
            <tr>
                <td style="width: 20%;">Total Gross:</td>
                <td style="width: 25%; text-align: right;">{{ $payroll->currency }} {{ number_format($payroll->gross_salary) }}</td>
                <td style="width: 15%; text-align: center;">Total Deductions:</td>
                <td style="width: 15%; text-align: right;">{{ $payroll->currency }} {{ number_format($payroll->total_deductions) }}</td>
                <td style="width: 10%; text-align: center;">Net Payable:</td>
                <td style="width: 15%; text-align: right;">{{ $payroll->currency }} {{ number_format($payroll->total_payable) }}</td>
            </tr>
        </table>
    </div>

</body>
</html>
