<?php

namespace Tests;

use App\Support\SchemaCache;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Le cache statique de SchemaCache survit entre les tests dans le même
        // processus PHP. Si un test précédent utilise une connexion différente
        // (ex. SQLite sans certaines colonnes), le cache peut rester invalide.
        SchemaCache::flush();
    }
}
