<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\EntrepriseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\MouvementStockController;
use App\Http\Controllers\CaisseController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\FicheDePaieController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ParametreController;
use App\Http\Controllers\FactureController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TiersController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\SuccursaleController;
use App\Http\Controllers\TransfertController;
use App\Models\Client;
use App\Models\Employe;
use App\Models\FicheDePaie;
use App\Models\Fournisseur;
use App\Models\Produit;






Route::get('/', function () {
    return Inertia::render('Home');
})->name('home');

Route::get('/register-entreprise', [EntrepriseController::class, 'create'])->name('entreprise.create');
Route::post('/register-entreprise', [EntrepriseController::class, 'store'])->name('entreprise.store');

Route::middleware(['auth'])->group(function () {
    Route::get('/upgrade', function () {
     return Inertia::render('Upgrade');
     })->name('upgrade');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/caisse', [CaisseController::class, 'index'])->name('caisse.index');
    Route::post('/caisse/initial', [CaisseController::class, 'storeInitial'])->name('caisse.initial');
    Route::get('/ressources-humaines', [\App\Http\Controllers\RessourcesHumainesController::class, 'index'])->name('ressources-humaines.index');
    Route::get('/profil', [ProfileController::class, 'index'])->name('profil.index');
    Route::get('/tiers', [TiersController::class, 'index'])->name('tiers.index');
    Route::post('/sync', [SyncController::class, 'store'])->name('sync.store');
    Route::post('/succursales', [SuccursaleController::class, 'store'])->middleware('plan:succursales')->name('succursales.store');
    Route::get('/succursales', [SuccursaleController::class, 'index'])->name('succursales.index');
    Route::get('/succursales/{succursale}', [SuccursaleController::class, 'show'])->name('succursales.show');
    Route::get('/succursales-exit', [SuccursaleController::class, 'exit'])->name('succursales.exit');
    Route::put('/succursales/{succursale}', [SuccursaleController::class, 'update'])->name('succursales.update');
    Route::delete('/succursales/{succursale}', [SuccursaleController::class, 'destroy'])->name('succursales.destroy');

    Route::get('/transferts', [TransfertController::class, 'index'])->name('transferts.index');
    Route::post('/transferts/caisse', [TransfertController::class, 'storeCaisse'])->name('transferts.caisse');
    Route::post('/transferts/stock', [TransfertController::class, 'storeStock'])->name('transferts.stock');
    Route::post('/transferts/{transfert}/approve', [TransfertController::class, 'approve'])->name('transferts.approve');
    Route::post('/transferts/{transfert}/reject', [TransfertController::class, 'reject'])->name('transferts.reject');
});

Route::match(['put', 'patch'], '/employes/{employe}', [EmployeController::class, 'update'])->middleware(['auth','can:super_admin-only'])->name('employes.update');
Route::delete('/employes/{employe}', [EmployeController::class, 'destroy'])->middleware(['auth','can:super_admin-only'])->name('employes.destroy');
Route::match(['put', 'patch'], '/employes', function (Request $request, EmployeController $controller) {
    $employe = Employe::findOrFail($request->input('id'));

    return $controller->update($request, $employe);
})->middleware(['auth','can:super_admin-only'])->name('employes.update.fallback');
Route::delete('/employes', function (Request $request, EmployeController $controller) {
    $employe = Employe::findOrFail($request->input('id'));

    return $controller->destroy($employe);
})->middleware(['auth','can:super_admin-only'])->name('employes.destroy.fallback');
Route::resource('employes', EmployeController::class)->except(['show', 'update', 'destroy'])->middleware('auth');


Route::match(['put', 'patch'], '/produits/{produit}', [ProductController::class, 'update'])->middleware(['auth','can:super_admin-only'])->name('produits.update');
Route::delete('/produits/{produit}', [ProductController::class, 'destroy'])->middleware(['auth','can:super_admin-only'])->name('produits.destroy');
Route::match(['put', 'patch'], '/produits', function (Request $request, ProductController $controller) {
    $produit = Produit::findOrFail($request->input('id'));

    return $controller->update($request, $produit);
})->middleware(['auth','can:super_admin-only'])->name('produits.update.fallback');
Route::delete('/produits', function (Request $request, ProductController $controller) {
    $produit = Produit::findOrFail($request->input('id'));

    return $controller->destroy($produit);
})->middleware(['auth','can:super_admin-only'])->name('produits.destroy.fallback');
// APRÈS — plan:produits uniquement sur store (création)
Route::resource('produits', ProductController::class)
    ->except(['show', 'update', 'destroy'])
    ->middleware(['auth'])
    ->only(['index', 'create', 'edit']);

Route::post('/produits', [ProductController::class, 'store'])
    ->middleware(['auth', 'plan:produits'])
    ->name('produits.store');
Route::match(['put', 'patch'], '/clients/{client}', [\App\Http\Controllers\ClientController::class, 'update'])->middleware(['auth','can:super_admin-only'])->name('clients.update');
Route::delete('/clients/{client}', [\App\Http\Controllers\ClientController::class, 'destroy'])->middleware(['auth','can:super_admin-only'])->name('clients.destroy');
Route::match(['put', 'patch'], '/clients', function (Request $request, \App\Http\Controllers\ClientController $controller) {
    $client = Client::findOrFail($request->input('id'));

    return $controller->update($request, $client);
})->middleware(['auth','can:super_admin-only'])->name('clients.update.fallback');
Route::delete('/clients', function (Request $request, \App\Http\Controllers\ClientController $controller) {
    $client = Client::findOrFail($request->input('id'));

    return $controller->destroy($client);
})->middleware(['auth','can:super_admin-only'])->name('clients.destroy.fallback');
Route::resource('clients', \App\Http\Controllers\ClientController::class)
    ->except(['show', 'update', 'destroy'])
    ->middleware(['auth'])
    ->only(['index', 'create', 'edit']);
Route::post('/clients', [\App\Http\Controllers\ClientController::class, 'store'])
    ->middleware(['auth', 'plan:clients'])
    ->name('clients.store');
Route::match(['put', 'patch'], '/fournisseurs/{fournisseur}', [\App\Http\Controllers\FournisseurController::class, 'update'])->middleware(['auth','can:super_admin-only'])->name('fournisseurs.update');
Route::delete('/fournisseurs/{fournisseur}', [\App\Http\Controllers\FournisseurController::class, 'destroy'])->middleware(['auth','can:super_admin-only'])->name('fournisseurs.destroy');
Route::match(['put', 'patch'], '/fournisseurs', function (Request $request, \App\Http\Controllers\FournisseurController $controller) {
    $fournisseur = Fournisseur::findOrFail($request->input('id'));

    return $controller->update($request, $fournisseur);
})->middleware(['auth','can:super_admin-only'])->name('fournisseurs.update.fallback');
Route::delete('/fournisseurs', function (Request $request, \App\Http\Controllers\FournisseurController $controller) {
    $fournisseur = Fournisseur::findOrFail($request->input('id'));

    return $controller->destroy($fournisseur);
})->middleware(['auth','can:super_admin-only'])->name('fournisseurs.destroy.fallback');
Route::resource('fournisseurs', \App\Http\Controllers\FournisseurController::class)
    ->except(['show', 'update', 'destroy'])
    ->middleware(['auth'])
    ->only(['index', 'create', 'edit']);
Route::post('/fournisseurs', [\App\Http\Controllers\FournisseurController::class, 'store'])
    ->middleware(['auth', 'plan:fournisseurs'])
    ->name('fournisseurs.store');

Route::get('/utilisateurs', function () {
    return Inertia::render('Utilisateurs/Index');
})->middleware('auth');

Route::get('/fiche-paye', function () {
    return Inertia::render('FichePaye/Index');
})->middleware('auth');

Route::middleware(['auth'])->group(function () {
    Route::post('/mouvement-stocks/generer-facture', [MouvementStockController::class, 'genererFactureVente'])
        ->name('mouvement-stocks.generer-facture');
    Route::post('/mouvement-stocks/generer-bon-entree', [MouvementStockController::class, 'genererBonEntree'])
        ->name('mouvement-stocks.generer-bon-entree');
    Route::resource('mouvement-stocks', MouvementStockController::class);
});

Route::resource('journals', JournalController::class)->only(['index','store'])->middleware('auth');




Route::resource('fiches', FicheDePaieController::class)->except(['show', 'edit', 'create'])->middleware('auth');
Route::put('/fiches/{fiche}/confirmer', [FicheDePaieController::class, 'confirmerPaiement'])
     ->middleware('auth')
     ->name('fiches.confirmer');
Route::post('/fiches/{fiche}/confirmer', [FicheDePaieController::class, 'confirmerPaiement'])
     ->middleware('auth')
     ->name('fiches.confirmer.post');
Route::match(['put', 'patch', 'post'], '/fiches/confirmer', function (Request $request, FicheDePaieController $controller) {
    $fiche = FicheDePaie::findOrFail($request->input('id') ?? $request->input('fiche_id'));

    return $controller->confirmerPaiement($fiche);
})->middleware('auth')->name('fiches.confirmer.fallback');


//Route::resource('users', UserController::class)->except(['create', 'edit', 'show']);
Route::resource('users', UserController::class)
    ->except(['show', 'update', 'destroy'])
    ->middleware(['auth'])
    ->only(['index', 'create', 'edit']);
Route::post('/users', [UserController::class, 'store'])
    ->middleware(['auth', 'plan:users'])
    ->name('users.store');
Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware(['auth','can:super_admin-only'])->name('users.destroy');
Route::post('/users/{user}/access', [UserController::class, 'updateAccess'])->middleware(['auth','can:super_admin-only'])->name('users.access');
Route::resource('users', UserController::class)->except(['show', 'update', 'destroy'])->middleware(['auth', 'plan:users']);

Route::middleware(['auth', 'plan:dette_tracking'])->group(function () {
    Route::get('/creances-dettes', [\App\Http\Controllers\CreancesDettesController::class, 'index'])
    ->middleware(['auth'])
    ->name('creances-dettes.index');
    Route::post('/creances-dettes/clients/{client}/paiement', [\App\Http\Controllers\CreancesDettesController::class, 'payerCreance'])->name('creances-dettes.clients.payer');
    Route::post('/creances-dettes/fournisseurs/{fournisseur}/paiement', [\App\Http\Controllers\CreancesDettesController::class, 'payerDette'])->name('creances-dettes.fournisseurs.payer');
    Route::get('/creances-dettes/clients/{client}', [\App\Http\Controllers\CreancesDettesController::class, 'detailClient'])->name('creances-dettes.clients.detail');
    Route::get('/creances-dettes/fournisseurs/{fournisseur}', [\App\Http\Controllers\CreancesDettesController::class, 'detailFournisseur'])->name('creances-dettes.fournisseurs.detail');
    Route::get('/creances-dettes/clients/{client}/pdf', [\App\Http\Controllers\CreancesDettesController::class, 'detailClientPdf'])->name('creances-dettes.clients.pdf');
    Route::get('/creances-dettes/fournisseurs/{fournisseur}/pdf', [\App\Http\Controllers\CreancesDettesController::class, 'detailFournisseurPdf'])->name('creances-dettes.fournisseurs.pdf');
    Route::post('/rapport/log', [\App\Http\Controllers\RapportController::class, 'logAction'])->name('rapport.log');
});




//Route::get('/parametres', [ParametreController::class, 'index'])->name('parametres.index');
//Route::post('/parametres', [ParametreController::class, 'store'])->name('parametres.store');
Route::middleware(['auth'])->group(function () {
    Route::get('/parametres', [ParametreController::class, 'index'])->name('parametres.index');
    Route::post('/parametres', [ParametreController::class, 'store'])->name('parametres.store');
    Route::put('/parametres/{parametre}', [ParametreController::class, 'update'])->middleware('can:super_admin-only')->name('parametres.update');
});


//Route::middleware(['auth'])->group(function () {
//    Route::get('/factures', [FactureController::class, 'index'])->name('factures.index');
//    Route::post('/factures', [FactureController::class, 'store'])->name('factures.store');
//    Route::get('/factures/{id}', [FactureController::class, 'show'])->name('factures.show');
//});

Route::resource('factures', FactureController::class)->middleware('auth');
Route::middleware(['auth'])->group(function () {
    Route::get('/archives/factures', [FactureController::class, 'archives'])->name('archives.factures');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/archives', [\App\Http\Controllers\ArchiveController::class, 'index'])->name('archives.index');
    Route::get('/archives/factures/{date}/pdf', [\App\Http\Controllers\ArchiveController::class, 'facturesPdf'])->name('archives.factures.pdf');
    Route::get('/archives/{type}/{date}/download', [\App\Http\Controllers\ArchiveController::class, 'download'])
        ->whereIn('type', ['journal', 'mouvement_stock', 'facture', 'bon_entree', 'caisse'])
        ->name('archives.download');
    Route::get('/archives/{type}/{date}/pdf', [\App\Http\Controllers\ArchiveController::class, 'pdf'])
        ->whereIn('type', ['journal', 'mouvement_stock', 'bon_entree', 'caisse'])
        ->name('archives.pdf');
    Route::get('/archives/{type}/{year}/{month}', [\App\Http\Controllers\ArchiveController::class, 'month'])
        ->where(['year' => '[0-9]{4}', 'month' => '[0-9]{1,2}'])
        ->name('archives.month');
    Route::get('/archives/{type}/{date}', [\App\Http\Controllers\ArchiveController::class, 'day'])
        ->where(['date' => '[0-9]{4}-[0-9]{2}-[0-9]{2}'])
        ->name('archives.day');
});

// Routes pour les historiques et rapports
Route::middleware(['auth'])->group(function () {
    // Historiques
    Route::get('/historique/mouvements-stock', [\App\Http\Controllers\HistoriqueController::class, 'mouvementStock'])->name('historique.mouvements-stock');
    Route::get('/historique/journaux', [\App\Http\Controllers\HistoriqueController::class, 'journal'])->name('historique.journaux');
    
    // Archives de mouvements de stock
    Route::get('/archives/mouvements-stock', [\App\Http\Controllers\MouvementStockArchiveController::class, 'index'])->name('archives.mouvements-stock');
    Route::get('/archives/mouvements-stock/{date}/download', [\App\Http\Controllers\MouvementStockArchiveController::class, 'download'])->name('archives.mouvements-stock.download');
    Route::get('/archives/mouvements-stock/{date}/preview', [\App\Http\Controllers\MouvementStockArchiveController::class, 'preview'])->name('archives.mouvements-stock.preview');
    
    // Archives de journaux
    Route::get('/archives/journaux', [\App\Http\Controllers\JournalArchiveController::class, 'index'])->name('archives.journaux');
    Route::get('/archives/journaux/{date}/download', [\App\Http\Controllers\JournalArchiveController::class, 'download'])->name('archives.journaux.download');
    Route::get('/archives/journaux/{date}/preview', [\App\Http\Controllers\JournalArchiveController::class, 'preview'])->name('archives.journaux.preview');
    
    // Rapport
    Route::get('/rapport', [\App\Http\Controllers\RapportController::class, 'index'])->name('rapport.index');
});

Route::get('/test-route', function () {
    return 'Test route is working';
});
Route::middleware(['auth', 'can:super_admin-only'])->prefix('admin')->group(function () {
    Route::get('/plans', [\App\Http\Controllers\Admin\PlanController::class, 'index'])->name('admin.plans');
    Route::post('/plans/{entreprise}/activate', [\App\Http\Controllers\Admin\PlanController::class, 'activate'])->name('admin.plans.activate');
    Route::post('/plans/{entreprise}/downgrade', [\App\Http\Controllers\Admin\PlanController::class, 'downgrade'])->name('admin.plans.downgrade');
});

// ══════════════════════════════════════
// OWNER — Interface propriétaire du site
// ══════════════════════════════════════
Route::prefix('owner')->name('owner.')->group(function () {
    // Auth (pas de middleware owner ici)
    Route::get('/login', [\App\Http\Controllers\Owner\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [\App\Http\Controllers\Owner\AuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [\App\Http\Controllers\Owner\AuthController::class, 'logout'])->name('logout');

    // Dashboard protégé
    Route::middleware('owner')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Admin\PlanController::class, 'index'])->name('dashboard');
        Route::post('/plans/{entreprise}/activate', [\App\Http\Controllers\Admin\PlanController::class, 'activate'])->name('plans.activate');
        Route::post('/plans/{entreprise}/downgrade', [\App\Http\Controllers\Admin\PlanController::class, 'downgrade'])->name('plans.downgrade');
    });
});

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';
