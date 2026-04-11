# 🧪 CHECKLIST DE TEST - PAGE RAPPORT JOURNALIER

**Date:** 16 janvier 2026

---

## ✅ Tests à Effectuer

### Test 1: Affichage du Bouton sur Dashboard
```
□ Aller à http://localhost:8000/dashboard
□ Vérifier la présence du bouton "Rapport Journalier" (couleur verte)
□ Vérifier qu'il est à côté du bouton "Ajouter employé"
□ Vérifier l'icône "assessment" s'affiche
□ Cliquer sur le bouton
□ Vérifier la redirection vers /rapport
```

### Test 2: Chargement de la Page Rapport
```
□ URL est: http://localhost:8000/rapport
□ Titre: "Rapport Journalier" s'affiche
□ Date du jour s'affiche (ex: 2026-01-16)
□ Bouton "Télécharger PDF" s'affiche
□ Sidebar avec 3 boutons de navigation s'affiche
```

### Test 3: Tableau Mouvements de Stock
```
□ En-têtes du tableau visibles:
  □ Produit
  □ Quantités
  □ Prix Unitaire
  □ Stock Initial
  □ Entrée
  □ Sortie
  □ Stock Final
  □ Utilisateur
  □ Heure

□ Si mouvements existent:
  □ Au moins 1 ligne de produit s'affiche
  □ Les sous-lignes (détail des opérations) s'affichent
  □ Les couleurs de code sont correctes:
    □ 🟦 Bleu pour Stock Initial
    □ 🟩 Vert pour Entrées
    □ 🟥 Rouge pour Sorties
    □ 🟪 Violet pour Stock Final

□ Si aucun mouvement:
  □ Message "Aucun mouvement de stock pour aujourd'hui" s'affiche
```

### Test 4: Cartes Résumé
```
□ 4 cartes visibles:
  □ Stock Initial (Valeur) - bordure bleue
  □ Entrées (Valeur) - bordure verte
  □ Sorties (Valeur) - bordure rouge
  □ Stock Final (Valeur) - bordure violette

□ Chaque carte affiche:
  □ Un label (ex: "Stock Initial (Valeur)")
  □ Un montant formaté en devise (ex: "1 300 000 FCFA")

□ Les montants sont corrects
```

### Test 5: Section Explication des Calculs
```
□ Titre "📊 Explication des Calculs" s'affiche

□ Colonne gauche (Calcul Stock Final):
  □ Stock Initial (Valeur)
  □ + Entrées (Valeur)
  □ - Sorties (Valeur)
  □ = Stock Final (Valeur)
  □ Fond bleu pour la ligne résultat

□ Colonne droite (Calcul Recette):
  □ Stock Initial
  □ + Entrées
  □ - Stock Final
  □ = Recette Journalière
  □ Fond vert pour la ligne résultat

□ Les calculs sont mathématiquement corrects
```

### Test 6: Tableau Dépenses
```
□ En-têtes du tableau visibles:
  □ Libellé de la Dépense
  □ Montant
  □ Utilisateur
  □ Heure

□ Si dépenses existent:
  □ Au moins 1 ligne s'affiche
  □ Montants affichés en rouge
  □ Ligne "Total Dépenses" au bas
  □ Total montant est correct

□ Si aucune dépense:
  □ Message "Aucune dépense pour aujourd'hui" s'affiche
```

### Test 7: Formatage des Données
```
□ Montants:
  □ Formatés avec séparateur millier (ex: "1 300 000")
  □ Symbole devise "FCFA" s'affiche
  □ Couleur appropriée (bleu, vert, rouge, violet)

□ Heures:
  □ Format HH:MM:SS (ex: "08:30:00")

□ Dates:
  □ Format YYYY-MM-DD (ex: "2026-01-16")

□ Noms:
  □ Affichés correctement (pas de "null" ou erreur)
```

### Test 8: Fonction Télécharger PDF
```
□ Cliquer sur "Télécharger PDF"
□ Menu d'impression s'ouvre
□ Vérifier que:
  □ La sidebar n'apparaît pas en PDF
  □ Les boutons n'apparaissent pas
  □ Les tableaux sont lisibles
  □ Les couleurs et formatage sont conservés
```

### Test 9: Navigation
```
□ Cliquer sur "Dashboard" dans la sidebar
□ Vérifier redirection vers /dashboard

□ Cliquer sur "Historique Rapports" dans la sidebar
□ Vérifier redirection vers /historique/mouvements-stock

□ Cliquer sur "Rapport Journalier" dans la sidebar
□ Vérifier reste sur la page (bouton actif en bleu)
```

### Test 10: Responsive Design
```
□ Sur desktop (1920px+):
  □ Tableau s'affiche correctement
  □ Colonnes bien espacées
  □ Cartes résumé en 4 colonnes

□ Sur tablet (768-1024px):
  □ Tableau reste lisible (peut scroller)
  □ Cartes résumé en 2 colonnes

□ Sur mobile (320-767px):
  □ Tableau reste lisible (peut scroller horizontal)
  □ Cartes résumé en 1 colonne
  □ Sidebar reste accessible
```

---

## 🧮 Tests de Calcul

### Cas 1: Stock Simple
```
Produit: Riz (prix_unitaire: 5000)
Stock Initial: 100 (valeur: 500 000)
Entrée: 50 (valeur: 250 000)
Sortie: 20 (valeur: 100 000)

Résultat Stock Final = 100 + 50 - 20 = 130 ✓
Résultat Valeur Stock Final = 130 × 5000 = 650 000 ✓
```

### Cas 2: Recette Journalière
```
Stock Initial (Valeur): 500 000
+ Entrées (Valeur): 250 000
- Stock Final (Valeur): 650 000

Recette = 500 000 + 250 000 - 650 000 = 100 000 ✓
```

### Cas 3: Plusieurs Produits
```
Produit 1: Initial 100, Entrée 50, Sortie 20, Final 130
Produit 2: Initial 30, Entrée 10, Sortie 5, Final 35

Stock Initial Total = 100 + 30 = 130
Entrée Total = 50 + 10 = 60
Sortie Total = 20 + 5 = 25
Stock Final Total = 130 + 60 - 25 = 165 ✓
```

---

## ⚠️ Cas Limites

### Cas 1: Aucun Mouvement
```
□ Tableau Mouvements: Message "Aucun mouvement"
□ Cartes Résumé: Affichent 0 ou montants corrects
□ Recette Journalière: 0
```

### Cas 2: Aucune Dépense
```
□ Tableau Dépenses: Message "Aucune dépense"
□ Total Dépenses: 0
```

### Cas 3: Produit Avec Stock Négatif
```
□ Stock final peut être négatif (possible si stock initial insuffisant)
□ Valeur calculée correctement même avec négatif
□ Affichage correct du montant négatif
```

### Cas 4: Utilisateur Non Assigné
```
□ Colonne Utilisateur: Affiche "N/A" au lieu de null/erreur
□ La page ne casse pas
```

---

## 🎯 Résultats Attendus

### Page Chargée avec Succès
- [ ] Aucune erreur console (F12)
- [ ] Aucune erreur dans `storage/logs/laravel.log`
- [ ] Temps de chargement < 2s
- [ ] Page entièrement rendue

### Données Affichées
- [ ] Au moins 1 mouvement de stock s'affiche (si créé aujourd'hui)
- [ ] Au moins 1 dépense s'affiche (si créée aujourd'hui)
- [ ] Cartes résumé montrent des valeurs cohérentes
- [ ] Calculs sont exacts

### Design Correct
- [ ] Sidebar visible et fonctionnelle
- [ ] Tableaux avec alternance de couleurs (si applicable)
- [ ] Texte lisible et bien formaté
- [ ] Couleurs cohérentes avec le design

---

## 📝 Notes de Test

```
Date du test: _____________
Testeur: _____________
Version: _____________

Observations:
_________________________________
_________________________________
_________________________________

Problèmes rencontrés:
_________________________________
_________________________________
_________________________________

Points à améliorer:
_________________________________
_________________________________
_________________________________
```

---

## ✅ Validation Finale

**Tous les tests réussis?**

- [ ] OUI → Page prête pour la production
- [ ] NON → Lister les problèmes ci-dessous:

```
Problème 1: ___________________________________
Solution: ___________________________________

Problème 2: ___________________________________
Solution: ___________________________________

Problème 3: ___________________________________
Solution: ___________________________________
```

---

