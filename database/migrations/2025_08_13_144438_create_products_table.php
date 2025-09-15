<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('product_type'); // si es producto de catalogo, materia prima, consumible, etc.
            $table->boolean('is_used_as_component')->default(false); // indica si el producto es usado como componente.
            $table->string('code')->unique(); // codigo de producto antes "part_number"
            $table->float('base_price')->nullable()->unsigned()->default(0); // precio base o precio de lista homologado
            $table->date('base_price_updated_at')->nullable(); // precio de la ultima actualizacion de precio base
            $table->boolean('is_sellable')->default(true); // indica si es un producto que se vende
            $table->boolean('is_purchasable')->default(true); // indica si es un producto que se compra
            $table->timestamp('archived_at')->nullable(); // fecha ar archivacion a obsoletos
            $table->string('material')->nullable(); // tipo de material con el cual esta hecho el producto Zamak, acero, abs, etc
            $table->float('large')->unsigned()->nullable();
            $table->float('height')->unsigned()->nullable();
            $table->float('width')->unsigned()->nullable();
            $table->float('diameter')->unsigned()->nullable();
            $table->string('measure_unit')->nullable();
            $table->string('currency')->nullable(); // moneda MXN, USD
            $table->string('caracteristics')->nullable(); // caracteristicas del producto como color, tamaño, forma, tipo de lñetra, etc
            $table->float('cost')->nullable()->unsigned()->default(0);
            $table->integer('min_quantity')->default(1);
            $table->integer('max_quantity')->nullable();
            $table->foreignId('brand_id')->constrained('brands'); 
            $table->foreignId('product_family_id')->nullable()->constrained('product_families'); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
