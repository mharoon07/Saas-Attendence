<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Employee Loans Report - {{ $month }} / {{ $year }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f4f4f4; }
    </style>
</head>
<body>
    <h2>Employee Loans Report — {{ $month }}/{{ $year }}</h2>
    <table>
        <thead>
            <tr>
                <th>Loan ID</th>
                <th>Employee Name</th>
                <th>Total Amount</th>
                <th>Deduction %</th>
                <th>Paid Amount</th>
                <th>Remaining Balance</th>
                <th>Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $r)
            <tr>
                <td>{{ $r->id }}</td>
                <td>{{ optional($r->employee)->name ?? 'N/A' }}</td>
                <td>{{ $r->total_amount }}</td>
                <td>{{ $r->deduction_percentage }}%</td>
                <td>{{ $r->paid_amount }}</td>
                <td>{{ $r->remaining_balance }}</td>
                <td>{{ $r->date }}</td>
                <td>{{ ucfirst($r->status) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
