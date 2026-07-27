<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Advance Payments Report - {{ $month }} / {{ $year }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f4f4f4; }
    </style>
</head>
<body>
    <h2>Advance Payments Report — {{ $month }}/{{ $year }}</h2>
    <table>
        <thead>
            <tr>
                <th>Advance ID</th>
                <th>Employee Name</th>
                <th>Advance Amount</th>
                <th>Remaining Amount</th>
                <th>Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $r)
            <tr>
                <td>{{ $r->id }}</td>
                <td>{{ optional($r->employee)->name ?? 'N/A' }}</td>
                <td>{{ $r->advance_amount }}</td>
                <td>{{ $r->remaining_amount }}</td>
                <td>{{ $r->date }}</td>
                <td>{{ ucfirst($r->status) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
