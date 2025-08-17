<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index()
    {
        //
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string'
        ]);

        Brand::create($request->all());
    }

    public function update(Request $request, Brand $brand)
    {
        $request->validate([
            'name' => 'required|string'
        ]);

        $brand->update($request->all());
    }
}
