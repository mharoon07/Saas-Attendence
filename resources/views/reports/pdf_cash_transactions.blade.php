<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cash Transactions Report - {{ $month }} / {{ $year }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f4f4f4; }
    </style>
</head>
<body>
    <h2>Cash Transactions Report — {{ $month }}/{{ $year }}</h2>
    <table>
        <thead>
            <tr>
                <th>Transaction ID</th>
                <th>Employee Name</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Description</th>
                <th>Reference</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $r)
            <tr>
                <td>{{ $r->id }}</td>
                <td>{{ optional($r->employee)->name ?? 'N/A' }}</td>
                <td>{{ ucfirst($r->transaction_type) }}</td>
                <td>{{ $r->amount }}</td>
                <td>{{ $r->date }}</td>
                <td>{{ $r->description }}</td>
                <td>{{ $r->reference }}</td>
                <td>{{ ucfirst($r->status) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
