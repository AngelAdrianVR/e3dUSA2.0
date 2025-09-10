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
        Schema::create('sample_tracking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_tracking_id')->constrained()->cascadeOnDelete();
            
            // Polymorphic relationship to link to either 'catalog_products' or 'new_product_proposals'
            $table->morphs('itemable'); // This will create `itemable_id` (unsignedBigInteger) and `itemable_type` (string)

            $table->unsignedFloat('quantity');
            
            // Specific feedback for this item
            $table->boolean('requires_modification')->default(false);
            $table->text('feedback')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sample_tracking_items');
    }
};
