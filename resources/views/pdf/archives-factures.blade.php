<!doctype html>
<html lang="fr">
  <head>
    <meta charset="utf-8">
    <title>Archives factures {{ $date }}</title>
    <style>
      body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
      h1 { font-size: 16px; margin-bottom: 8px; }
      .facture { border: 1px solid #ddd; padding: 10px; margin-bottom: 12px; }
      table { width: 100%; border-collapse: collapse; margin-top: 6px; }
      th, td { border: 1px solid #ddd; padding: 6px; }
      th { background: #f3f4f6; text-align: left; }
      .right { text-align: right; }
      .totaux { margin-top: 6px; text-align: right; }
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
        @if (!empty($logo_url))
          <div class="header-logo" style="text-align: {{ $logo_position }};">
            <img src="{{ $logo_url }}" style="height: 50px;">
          </div>
        @endif
      </div>
    </div>
    <h1>Archives factures du {{ $date }}</h1>

    @foreach ($factures as $f)
      <div class="facture">
        <div><strong>Facture:</strong> {{ $f['numero'] ?? '-' }}</div>
        <div><strong>Date:</strong> {{ $f['date_facture'] ?? '-' }}</div>
        <div><strong>Client:</strong> {{ $f['client'] ?? '-' }}</div>

        <table>
          <thead>
            <tr>
              <th>Désignation</th>
              <th class="right">Quantité</th>
              <th class="right">PU</th>
              <th class="right">PT</th>
            </tr>
          </thead>
          <tbody>
            @foreach (($f['lignes'] ?? []) as $l)
              <tr>
                <td>{{ $l['designation'] ?? '-' }}</td>
                <td class="right">{{ $l['quantite'] ?? 0 }}</td>
                <td class="right">{{ number_format((float) ($l['prix_ttc'] ?? 0), 2, '.', ',') }}</td>
                <td class="right">{{ number_format((float) ($l['total'] ?? 0), 2, '.', ',') }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>

        <div class="totaux">
          <div>PV HT: {{ number_format((float) ($f['total_ht'] ?? 0), 2, '.', ',') }}</div>
          <div>TVA: {{ number_format((float) ($f['total_tva'] ?? 0), 2, '.', ',') }}</div>
          <div><strong>PV TTC: {{ number_format((float) ($f['total_ttc'] ?? 0), 2, '.', ',') }}</strong></div>
        </div>
      </div>
    @endforeach
  </body>
</html>
