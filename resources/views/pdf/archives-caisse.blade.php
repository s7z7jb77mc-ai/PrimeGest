<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Archives Caisse</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; }
        h2 { margin: 0 0 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f3f4f6; }
        .text-right { text-align: right; }
        .header { margin-bottom: 10px; }
        .meta { font-size: 11px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Archives de caisse - {{ $date }}</h2>
        <div class="meta">
            @if(!empty($parametres?->nom_entreprise))
                {{ $parametres->nom_entreprise }}<br>
            @endif
            @if(!empty($parametres?->adresse))
                {{ $parametres->adresse }}<br>
            @endif
            @if(!empty($parametres?->telephone))
                {{ $parametres->telephone }}
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th class="text-right">Entrée</th>
                <th class="text-right">Sortie</th>
                <th class="text-right">Solde</th>
            </tr>
        </thead>
        <tbody>
            @forelse($archives as $row)
                <tr>
                    <td>{{ $row['date_operation'] ?? '-' }}</td>
                    <td>{{ $row['description'] ?? '-' }}</td>
                    <td class="text-right">{{ number_format((float) ($row['entree'] ?? 0), 2, ',', ' ') }}</td>
                    <td class="text-right">{{ number_format((float) ($row['sortie'] ?? 0), 2, ',', ' ') }}</td>
                    <td class="text-right">{{ number_format((float) ($row['solde'] ?? 0), 2, ',', ' ') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Aucune donnée</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
