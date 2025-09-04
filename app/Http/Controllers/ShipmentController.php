<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function index()
    {
        //
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        //
    }

    public function show(Shipment $shipment)
    {
        //
    }

    public function edit(Shipment $shipment)
    {
        //
    }

    public function update(Request $request, Shipment $shipment)
    {
        //  $shipment->sale->updateStatus(); // <--- Llamas al método para actualizar estatus de venta
    }

    public function destroy(Shipment $shipment)
    {
        //
    }
}
