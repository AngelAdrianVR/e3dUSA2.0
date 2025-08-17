<?php

namespace App\Http\Controllers;

use App\Models\ProductFamily;
use Illuminate\Http\Request;

class ProductFamilyController extends Controller
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
        $validate_data = $request->validate([
            'name' => 'required|string',
            'key' => 'required|string|min:1|max:3',
        ]);

        ProductFamily::create($validate_data);
    }

    public function show(ProductFamily $product_family)
    {
        //
    }

    public function edit(ProductFamily $product_family)
    {
        //
    }

    public function update(Request $request, ProductFamily $product_family)
    {
        $validate_data = $request->validate([
            'name' => 'required|string',
            'key' => 'required|string|min:1|max:3',
        ]);

        $product_family->update($validate_data);
    }

    public function destroy(ProductFamily $product_family)
    {
        //
    }
}
