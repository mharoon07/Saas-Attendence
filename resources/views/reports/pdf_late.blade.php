<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Late Report - {{ $month }} / {{ $year }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; }
        th { background: #f4f4f4; }
    </style>
</head>
<body>
    <h2>Late Entries — {{ $month }}/{{ $year }}</h2>
    <table>
        <thead>
            <tr>
                <th>Employee ID</th>
                <th>Employee Name</th>
                <th>Date</th>
                <th>Status</th>
                <th>Sign In</th>
                <th>Sign Off</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $a)
            <tr>
                <td>{{ $a->employee_id }}</td>
                <td>{{ optional($a->employee)->name }}</td>
                <td>{{ $a->date }}</td>
                <td>{{ $a->status }}</td>
                <td>{{ $a->sign_in_time }}</td>
                <td>{{ $a->sign_off_time }}</td>
                <td>{{ $a->notes }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>