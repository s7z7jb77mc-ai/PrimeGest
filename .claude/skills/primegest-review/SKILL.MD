Utilise cette skill pour auditer, relire ou analyser le code de PrimeGest (Laravel + Vue.js + Pinia + MySQL). Déclenche sur : revue de code, audit de sécurité, recherche de failles, vérification avant commit ou déploiement, analyse de permissions, revue d'upload ou de paiement. Stack : Laravel + Sanctum, Vue.js + Pinia + Tailwind, MySQL/MariaDB.Audit & Code Review — PrimeGest
Revue systématique du code avec focus sécurité, qualité et patterns Laravel/Vue.js.
Étape 1 : Collecter les changements
bash# Scope des modifications
git diff --stat HEAD
git diff HEAD

# Fichiers modifiés récemment
git log --oneline -10
git show --stat HEAD
Explorer aussi l'arborescence pour comprendre le contexte :
bashls app/Http/Controllers/
ls app/Models/
ls app/Policies/
ls resources/js/

Étape 2 : Audit Laravel (Backend)
🔴 Authentification & Sanctum (CRITIQUE)

 Toutes les routes API sensibles sont protégées par auth:sanctum
 Les tokens Sanctum ont une expiration définie (SANCTUM_TOKEN_EXPIRATION dans .env)
 Pas de route sensible accessible sans middleware
 Révocation des tokens à la déconnexion ($request->user()->currentAccessToken()->delete())
 Pas de token exposé dans les logs ou les réponses API

php// ✅ Correct
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('paiements', PaiementController::class);
});

// ❌ Dangereux
Route::get('paiements', [PaiementController::class, 'index']); // pas de middleware

🔴 Rôles & Permissions (CRITIQUE)

 Chaque action sensible vérifie les permissions via Gate ou Policy
 Pas de vérification de rôle uniquement côté Vue.js (toujours vérifier côté serveur)
 Les Policies sont enregistrées dans AuthServiceProvider
 Utilisation de $this->authorize() dans les controllers ou can() dans les routes
 Pas d'accès direct à $request->user()->role === 'admin' sans abstraction
 Les scopes Sanctum limitent bien les capacités des tokens

php// ✅ Correct
public function update(Request $request, Paiement $paiement)
{
    $this->authorize('update', $paiement);
    // ...
}

// ❌ Dangereux — vérification faible
if ($request->user()->role == 'admin') { ... }

🔴 Upload de Fichiers (CRITIQUE)

 Validation stricte du type MIME (mimes:pdf,jpg,png — pas mimetypes seul)
 Validation de la taille maximale (max:2048)
 Stockage hors du dossier public/ (utiliser storage/app/private/)
 Nom de fichier régénéré par Laravel ($file->store()) — jamais le nom original
 Pas d'exécution possible des fichiers uploadés (pas de .php accepté)
 Accès aux fichiers via une route contrôlée, pas un lien direct

php// ✅ Correct
$request->validate([
    'document' => 'required|file|mimes:pdf,jpg,png|max:5120',
]);
$path = $request->file('document')->store('documents', 'private');

// ❌ Dangereux
$file->move(public_path('uploads'), $file->getClientOriginalName());

🔴 Paiements (CRITIQUE)

 Montants calculés côté serveur — jamais depuis $request->amount
 Vérification du statut de paiement via webhook signé (pas juste le retour frontend)
 Clés API de paiement uniquement dans .env, jamais dans le code
 Idempotence des transactions (pas de double débit possible)
 Logs de transactions sans données de carte bancaire

php// ❌ Dangereux — montant contrôlé par le client
$montant = $request->input('montant');

// ✅ Correct — montant récupéré depuis la base
$commande = Commande::findOrFail($request->commande_id);
$montant = $commande->montant_total;

🔴 Injection SQL & Eloquent (CRITIQUE)

 Pas de requêtes SQL brutes avec des variables non échappées
 DB::raw() utilisé uniquement avec des valeurs bindées
 Pas de whereRaw() avec interpolation de chaîne directe
 Les IDs dans les URLs sont validés (Route Model Binding préféré)

php// ❌ Dangereux
DB::select("SELECT * FROM users WHERE email = '$email'");

// ✅ Correct
DB::select("SELECT * FROM users WHERE email = ?", [$email]);
// ou mieux
User::where('email', $email)->first();

🔴 Validation des entrées (CRITIQUE)

 Chaque Controller utilise un FormRequest ou $request->validate()
 Pas de $request->all() passé directement à create() sans $fillable
 Les champs $fillable dans les Models sont explicitement définis
 Pas de $guarded = [] sans raison documentée

php// ❌ Dangereux
User::create($request->all());

// ✅ Correct
User::create($request->validated());
// avec $fillable défini dans le Model

⚠️ Exposition de données (WARNING)

 Les API Resources (JsonResource) filtrent les champs sensibles
 Pas de ->toArray() ou ->toJson() direct sur un Model avec champs sensibles
 Les champs $hidden du Model incluent : password, remember_token, tokens
 Pagination sur toutes les listes (pas de ->get() sans limite)


⚠️ Gestion des erreurs (WARNING)

 Pas de stack trace exposée en production (APP_DEBUG=false)
 Les exceptions métier sont catchées et retournent des messages génériques
 Les logs n'incluent pas de données personnelles ou sensibles
 Handler.php gère proprement les AuthenticationException et ValidationException


Étape 3 : Audit Vue.js + Pinia (Frontend)
🔴 Sécurité frontend (CRITIQUE)

 Pas de v-html avec des données venant de l'API (risque XSS)
 Les tokens Sanctum sont stockés dans un cookie httpOnly — pas dans localStorage
 Pas de logique d'autorisation uniquement côté Vue (toujours doublée côté API)
 Les clés API tierces ne sont pas dans le code frontend versionné

vue<!-- ❌ Dangereux — XSS possible -->
<div v-html="commentaire.contenu"></div>

<!-- ✅ Correct -->
<div>{{ commentaire.contenu }}</div>

🔴 Store Pinia (CRITIQUE)

 Les données sensibles (token, infos paiement) ne persistent pas dans localStorage via persist
 Les actions Pinia qui appellent l'API gèrent les erreurs (try/catch)
 Pas de données d'un utilisateur qui "fuient" dans le store d'un autre (logout propre)
 $reset() appelé au logout pour vider les stores

js// ✅ Correct — logout propre
async logout() {
  await api.post('/logout')
  useAuthStore().$reset()
  useUserStore().$reset()
  router.push('/login')
}

⚠️ Appels API (WARNING)

 Toutes les requêtes passent par un composable ou service centralisé (pas de fetch éparpillé)
 Les erreurs 401 redirigent vers le login (intercepteur Axios)
 Les erreurs 403 affichent un message clair sans exposer de détails
 Pas de données sensibles dans les paramètres d'URL (?token=xxx)

js// ✅ Intercepteur Axios pour 401
axios.interceptors.response.use(null, (error) => {
  if (error.response?.status === 401) {
    router.push('/login')
  }
  return Promise.reject(error)
})

⚠️ Qualité composants (WARNING)

 Les props sont typées et validées (defineProps avec types)
 Pas de logique métier dans les composants (déplacée dans composables)
 Les événements émis sont documentés (defineEmits)
 Pas de console.log oublié en production


Étape 4 : Audit Base de données MySQL
🔴 Structure & Migrations (CRITIQUE)

 Toutes les clés étrangères ont des contraintes foreign() dans les migrations
 Les colonnes sensibles sont chiffrées au repos si nécessaire (ex: IBAN, numéro de carte)
 Pas de données sensibles dans les colonnes sans index approprié (performance + sécurité)
 Les migrations sont irréversibles documentées (down() qui fait // irreversible)


⚠️ Performances & N+1 (WARNING)

 Pas de requête N+1 (utiliser with('relation') pour les eager loads)
 Les listes paginées ont des index sur les colonnes triées/filtrées
 Pas de SELECT * sur des tables volumineuses

php// ❌ N+1
$users = User::all();
foreach ($users as $user) {
    echo $user->role->name; // requête par user
}

// ✅ Eager loading
$users = User::with('role')->get();

Étape 5 : Sécurité générale
🔴 Configuration (CRITIQUE)

 .env non versionné (présent dans .gitignore)
 APP_DEBUG=false en production
 APP_ENV=production en production
 Clés de chiffrement (APP_KEY) non exposées
 Headers de sécurité configurés (CSP, X-Frame-Options, HSTS)

🔴 CSRF & CORS (CRITIQUE)

 CORS configuré strictement (config/cors.php — pas allowed_origins: ['*'] en prod)
 Protection CSRF active sur les routes web
 Sanctum appelle /sanctum/csrf-cookie avant les requêtes POST depuis Vue.js


Étape 6 : Synthèse du rapport
Pour chaque problème trouvé, fournir :

Localisation : fichier + ligne
Problème : ce qui est incorrect et pourquoi c'est risqué
Fix : correction avec exemple de code si utile
Sévérité : 🔴 Critique / ⚠️ Warning / 💡 Suggestion

Définitions de sévérité
NiveauSignification🔴 CritiqueFaille exploitable, perte de données, accès non autorisé possible⚠️ WarningMauvaise pratique, dette technique, risque futur💡 SuggestionAmélioration de lisibilité, performance ou maintenabilité
Résumé final
Conclure avec :

Nombre de problèmes par sévérité
Les 3 risques les plus urgents à corriger
Verdict global : ✅ Prêt à merger / ⚠️ Corrections nécessaires / 🔴 Bloquant


Anti-patterns à signaler systématiquement

dd() ou dump() oublié dans le code
->get() sans pagination sur une liste
Token ou mot de passe hardcodé dans le code
try/catch vide qui avale silencieusement les erreurs
$request->all() passé directement à create() ou update()
v-html avec données dynamiques
Données sensibles dans les logs Laravel
Route sans middleware auth:sanctum qui devrait en avoir un
localStorage utilisé pour stocker le token Sanctum