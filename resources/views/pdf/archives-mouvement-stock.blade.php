<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8">
    <title>Archives mouvement stock {{ $date }}</title>
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
    <h1>Mouvements de stock du {{ $date }}</h1>
    <table>
      <thead>
        <tr>
          <th>Date & heure</th>
          <th>Type</th>
          <th>Produit</th>
          <th class="right">Quantité</th>
          <th class="right">PU</th>
          <th class="right">PT</th>
          <th>Utilisateur</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($archives as $a)
          <tr>
            <td>{{ $a['date'] ?? '-' }}</td>
            <td>{{ $a['type'] ?? '-' }}</td>
            <td>{{ $a['produit'] ?? '-' }}</td>
            <td class="right">{{ $a['quantite'] ?? 0 }}</td>
            <td class="right">{{ number_format((float) ($a['prix_unitaire'] ?? 0), 2, '.', ',') }}</td>
            <td class="right">{{ number_format((float) ($a['prix_total'] ?? 0), 2, '.', ',') }}</td>
            <td>{{ $a['user'] ?? '-' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </body>
</html>
