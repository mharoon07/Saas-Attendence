<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Report - {{ $month }} / {{ $year }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; }
        th { background: #f4f4f4; }
    </style>
</head>
<body>
    <h2>Attendance Report — {{ $month }}/{{ $year }}</h2>
    <table>
        <thead>
            <tr>
                <th>Employee ID</th>
                <th>Employee Name</th>
                <th>Days Present</th>
                <th>Days Absent</th>
                <th>Late Count</th>
                <th>Total Hours</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $r)
            <tr>
                <td>{{ $r['id'] }}</td>
                <td>{{ $r['name'] }}</td>
                <td>{{ $r['attended'] }}</td>
                <td>{{ $r['absent'] }}</td>
                <td>{{ $r['late'] }}</td>
                <td>{{ $r['hours'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>