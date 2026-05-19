<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8">
    <title>Archives journal {{ $date }}</title>
    <style>
      body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
      h1 { font-size: 16px; margin-bottom: 8px; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #ddd; padding: 6px; }
      th { background: #f3f4f6; text-align: left; }
      .right { text-align: right; }
      .header { margin-bottom: 12px; }
      .header-top { display: flex; justify-content: space-between; align-items: flex-start; }
      .header-info { font-size: 12px; line-height: 1.4; }
      .header-logo img { height: 50px; }
    </style>
  </head>
  <body>
    <div class="header">
      <div class="header-top">
        <div class="header-info">
          <div><strong>{{ $parametres?->nom_entreprise ?? 'Entreprise' }}</strong></div>
          <div>{{ $parametres?->adresse ?? '-' }}</div>
          <div>{{ $parametres?->telephone ?? '-' }}</div>
          <div>Date: {{ $date }}</div>
        </div>
        @if (!empty($parametres?->logo_url))
          <div class="header-logo">
            <img src="{{ $parametres->logo_url }}">
          </div>
        @endif
      </div>
    </div>
    <h1>Journal du {{ $date }}</h1>
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Heure</th>
          <th>Type</th>
          <th>Produit</th>
          <th>Description</th>
          <th>Utilisateur</th>
          <th class="right">Montant</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($archives as $a)
          <tr>
            <td>{{ $a['date'] ?? '-' }}</td>
            <td>{{ $a['heure'] ?? '-' }}</td>
            <td>{{ $a['type'] ?? '-' }}</td>
            <td>{{ $a['produit'] ?? '-' }}</td>
            <td>{{ $a['description'] ?? '-' }}</td>
            <td>{{ $a['user'] ?? '-' }}</td>
            <td class="right">{{ number_format((float) ($a['montant'] ?? 0), 2, '.', ',') }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </body>
</html>
