<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payroll Report - {{ $month }} / {{ $year }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; }
        th { background: #f4f4f4; }
    </style>
</head>
<body>
    <h2>Payroll Report — {{ $month }}/{{ $year }}</h2>
    <table>
        <thead>
            <tr>
                <th>Employee ID</th>
                <th>Employee Name</th>
                <th>Base</th>
                <th>Performance</th>
                <th>Rewards</th>
                <th>Incentives</th>
                <th>Reimbursements</th>
                <th>Shift Differentials</th>
                <th>Overtime</th>
                <th>Commissions</th>
                <th>Income Tax</th>
                <th>Social Security</th>
                <th>Health Insurance</th>
                <th>Retirement</th>
                <th>Benefits</th>
                <th>Union Fees</th>
                <th>Undertime</th>
                <th>Total Additions</th>
                <th>Total Deductions</th>
                <th>Total Payable</th>
                <th>Due Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        @foreach($data as $p)
            <tr>
                <td>{{ $p->employee_id }}</td>
                <td>{{ optional($p->employee)->name }}</td>
                <td>{{ $p->base }}</td>
                <td>{{ $p->performance_multiplier }}</td>
                <td>{{ optional($p->additions)->rewards ?? 0 }}</td>
                <td>{{ optional($p->additions)->incentives ?? 0 }}</td>
                <td>{{ optional($p->additions)->reimbursements ?? 0 }}</td>
                <td>{{ optional($p->additions)->shift_differentials ?? 0 }}</td>
                <td>{{ optional($p->additions)->overtime ?? 0 }}</td>
                <td>{{ optional($p->additions)->commissions ?? 0 }}</td>
                <td>{{ optional($p->deductions)->income_tax ?? 0 }}</td>
                <td>{{ optional($p->deductions)->social_security_contributions ?? 0 }}</td>
                <td>{{ optional($p->deductions)->health_insurance ?? 0 }}</td>
                <td>{{ optional($p->deductions)->retirement_plan ?? 0 }}</td>
                <td>{{ optional($p->deductions)->benefits ?? 0 }}</td>
                <td>{{ optional($p->deductions)->union_fees ?? 0 }}</td>
                <td>{{ optional($p->deductions)->undertime ?? 0 }}</td>
                <td>{{ $p->total_additions }}</td>
                <td>{{ $p->total_deductions }}</td>
                <td>{{ $p->total_payable }}</td>
                <td>{{ $p->due_date }}</td>
                <td>{{ $p->status ? 'Paid' : ($p->is_reviewed ? 'Reviewed' : 'Pending') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>