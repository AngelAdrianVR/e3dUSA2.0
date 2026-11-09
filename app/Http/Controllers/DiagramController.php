<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class DiagramController extends Controller
{
    /**
     * Diagrama visual del proceso de venta y operativo (documentación interna).
     */
    public function index()
    {
        return Inertia::render('Diagram/Index');
    }
}
