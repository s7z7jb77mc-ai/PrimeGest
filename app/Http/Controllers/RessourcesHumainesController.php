<?php

namespace App\Http\Controllers;

use App\Models\Employe;
use App\Models\FicheDePaie;
use Inertia\Inertia;

class RessourcesHumainesController extends Controller
{
    public function index()
    {
        $entrepriseId = auth()->user()->entreprise_id;
        $employesCount = Employe::where('entreprise_id', $entrepriseId)->count();
        $fichesCount = FicheDePaie::where('entreprise_id', $entrepriseId)->count();

        return Inertia::render('RessourcesHumaines/Index', [
            'employesCount' => $employesCount,
            'fichesCount' => $fichesCount,
        ]);
    }
}
