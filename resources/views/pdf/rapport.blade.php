<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Rapport {{ $rapport['periode_detaillee'] ?? '' }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 0; padding: 16px; }
    h1 { font-size: 16px; margin-bottom: 4px; }
    h2 { font-size: 13px; margin: 12px 0 6px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th, td { border: 1px solid #ccc; padding: 5px 7px; }
    th { background: #f3f4f6; text-align: left; }
    .right { text-align: right; }
    .header { margin-bottom: 14px; display: flex; justify-content: space-between; }
    .header-info { line-height: 1.5; }
    .green { color: #166534; }
    .red { color: #991b1b; }
    .summary-grid { display: flex; gap: 20px; margin-bottom: 10px; }
    .summary-col { flex: 1; }
    .summary-row { display: flex; justify-content: space-between; padding: 3px 0; border-bottom: 1px solid #eee; }
    .bold { font-weight: bold; }
  </style>
</head>
<body>
  <div class="header">
    <div class="header-info">
      <div class="bold">{{ $parametres?->nom_entreprise ?? 'Entreprise' }}</div>
      <div>{{ $parametres?->adresse ?? '' }}</div>
      <div>{{ $parametres?->telephone ?? '' }}</div>
    </div>
    <div class="header-info" style="text-align:right;">
      <div class="bold">{{ $rapport['periode_detaillee'] ?? '' }}</div>
      <div>Devise : {{ $devise }}</div>
      <div>Généré le : {{ now()->format('d/m/Y H:i') }}</div>
    </div>
  </div>

  <h1>{{ $titre }}</h1>

  {{-- Résumé financier --}}
  <h2>Résumé financier</h2>
  <div class="summary-grid">
    <div class="summary-col">
      <div class="summary-row"><span>Stock initial :</span><span>{{ number_format($rapport['resume']['stock_initial_valeur'] ?? 0, 2) }} {{ $devise }}</span></div>
      <div class="summary-row"><span>Total entrées :</span><span class="green">{{ number_format($rapport['resume']['total_entrees'] ?? 0, 2) }} {{ $devise }}</span></div>
      <div class="summary-row"><span>Total sorties :</span><span class="red">{{ number_format($rapport['resume']['total_sorties'] ?? 0, 2) }} {{ $devise }}</span></div>
      <div class="summary-row bold"><span>Stock final :</span><span>{{ number_format($rapport['resume']['stock_final_valeur'] ?? 0, 2) }} {{ $devise }}</span></div>
    </div>
    @if(!empty($rapport['caisse']))
    <div class="summary-col">
      <div class="summary-row"><span>Entrées caisse :</span><span class="green">{{ number_format($rapport['caisse']['entrees_periode'] ?? 0, 2) }} {{ $devise }}</span></div>
      <div class="summary-row"><span>Sorties caisse :</span><span class="red">{{ number_format($rapport['caisse']['sorties_periode'] ?? 0, 2) }} {{ $devise }}</span></div>
      <div class="summary-row"><span>Solde période :</span><span>{{ number_format($rapport['caisse']['solde_periode'] ?? 0, 2) }} {{ $devise }}</span></div>
      <div class="summary-row bold"><span>Solde total :</span><span>{{ number_format($rapport['caisse']['solde_total'] ?? 0, 2) }} {{ $devise }}</span></div>
    </div>
    @endif
  </div>

  {{-- Mouvements de stock --}}
  @if(!empty($rapport['mouvements']))
  <h2>Mouvements de stock</h2>
  <table>
    <thead>
      <tr>
        <th>Produit</th>
        <th class="right">PU</th>
        <th class="right">Entrées</th>
        <th class="right">Sorties</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rapport['mouvements'] as $m)
      <tr>
        <td>{{ $m['produit_nom'] }}</td>
        <td class="right">{{ number_format($m['prix_unitaire'] ?? 0, 2) }}</td>
        <td class="right green">{{ $m['entrees'] ?? 0 }}</td>
        <td class="right red">{{ $m['sorties'] ?? 0 }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  {{-- Dépenses --}}
  @if(!empty($rapport['depenses']))
  <h2>Dépenses</h2>
  <table>
    <thead>
      <tr>
        <th>Libellé</th>
        <th>Utilisateur</th>
        <th>Heure</th>
        <th class="right">Montant</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rapport['depenses'] as $d)
      <tr>
        <td>{{ $d['libelle'] }}</td>
        <td>{{ $d['user'] }}</td>
        <td>{{ $d['heure'] }}</td>
        <td class="right">{{ number_format($d['montant'] ?? 0, 2) }} {{ $devise }}</td>
      </tr>
      @endforeach
      <tr style="font-weight:bold; background:#f9f9f9;">
        <td colspan="3">Total dépenses</td>
        <td class="right">{{ number_format($rapport['totalDepenses'] ?? 0, 2) }} {{ $devise }}</td>
      </tr>
    </tbody>
  </table>
  @endif
</body>
</html>
