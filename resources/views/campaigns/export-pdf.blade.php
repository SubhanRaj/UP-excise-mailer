<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: sans-serif; font-size: 11px; color: #1e293b; }
    h1 { font-size: 16px; margin-bottom: 2px; }
    p.meta { color: #64748b; margin-top: 0; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
    th { background: #f1f5f9; }
    .yes { color: #059669; font-weight: bold; }
    .no { color: #94a3b8; }
</style>
</head>
<body>
    <h1>{{ $campaign->name }} — Recipients</h1>
    <p class="meta">Exported {{ now()->ist()->format('d M Y, H:i') }} &middot; {{ $recipients->count() }} recipients</p>
    <table>
        <thead>
            <tr><th>Name</th><th>Email</th><th>Status</th><th>Sent At</th><th>Responded</th></tr>
        </thead>
        <tbody>
            @foreach($recipients as $recipient)
            <tr>
                <td>{{ $recipient->name ?: '—' }}</td>
                <td>{{ $recipient->email }}</td>
                <td>{{ $recipient->status === 'pending' ? 'Waiting' : ucfirst($recipient->status) }}</td>
                <td>{{ $recipient->sent_at?->ist()->format('d M Y, H:i') ?? '—' }}</td>
                <td class="{{ $recipient->responded_at ? 'yes' : 'no' }}">{{ $recipient->responded_at ? 'Yes' : 'No' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
