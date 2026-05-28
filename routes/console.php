<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Vérification des abonnements expirés — chaque jour à 02:00
Schedule::command('subscriptions:check')->dailyAt('02:00')->withoutOverlapping();

// Archivage quotidien (journal, mouvements, factures) — chaque jour à 23:59
Schedule::command('archive:unified --only=journal,mouvement_stock,facture')->dailyAt('23:59')->withoutOverlapping();

// Génération des rapports — chaque jour à 23:45
Schedule::command('generate:rapports')->dailyAt('23:45')->withoutOverlapping();

// Rollover mensuel de la caisse — 1er du mois à 00:05
Schedule::command('caisse:monthly-rollover')->monthlyOn(1, '00:05')->withoutOverlapping();
