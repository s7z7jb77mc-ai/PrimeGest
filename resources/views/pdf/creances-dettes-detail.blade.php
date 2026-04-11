<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8">
    <title>Détail créances & dettes</title>
    <style>
      body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
      h1 { font-size: 16px; margin-bottom: 8px; }
      table { width: 100%; border-collapse: collapse; margin-top: 6px; }
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
          <div>Date: {{ now()->toDateString() }}</div>
        </div>
        @if (!empty($parametres?->logo_url))
          <div class="header-logo">
            <img src="{{ $parametres->logo_url }}">
          </div>
        @endif
      </div>
    </div>

    <h1>{{ $entityType === 'client' ? 'Client' : 'Fournisseur' }} : {{ $entityName }}</h1>
    <div>Coordonnées</div>
    <div>Téléphone : {{ $entityInfo['telephone'] ?? '-' }}</div>
    <div>Adresse : {{ $entityInfo['adresse'] ?? '-' }}</div>

    <table>
      <thead>
        <tr>
          <th>Mois</th>
          <th class="right">Achat ({{ $devise }})</th>
          <th class="right">{{ $entityType === 'client' ? 'Créance' : 'Dette' }} ({{ $devise }})</th>
          <th class="right">Paiement ({{ $devise }})</th>
          <th class="right">Réduction ({{ $devise }})</th>
          <th>Récupération</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($summary as $row)
          <tr>
            <td>{{ $row['label'] ?? '-' }}</td>
            <td class="right">{{ number_format((float) ($row['achat'] ?? 0), 2, '.', ',') }}</td>
            <td class="right">{{ number_format((float) (($row['creance'] ?? $row['dette'] ?? 0)), 2, '.', ',') }}</td>
            <td class="right">{{ number_format((float) ($row['paiement'] ?? 0), 2, '.', ',') }}</td>
            <td class="right">{{ number_format((float) ($row['reduction'] ?? 0), 2, '.', ',') }}</td>
            <td>{{ $row['recuperation_date'] ?? '-' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="6">Aucune donnée disponible</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </body>
</html>
