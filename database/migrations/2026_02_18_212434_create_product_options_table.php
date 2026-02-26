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
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('name'); 
            // Example: Base Type, Flower Count, Size, Color

            $table->enum('type', ['select', 'radio', 'number', 'text']);

            $table->boolean('is_required')->default(false);

            // For number type
            $table->integer('min_value')->nullable();
            $table->integer('max_value')->nullable();
            $table->decimal('price_per_unit', 10, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_options');
    }
};
