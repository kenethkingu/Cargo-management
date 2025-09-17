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
        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->string('cargo_no')->unique();
            $table->string('cargo_type');
            $table->integer('cargo_size');
            $table->integer('weight')->nullable();
            $table->string('remarks')->nullable();
            $table->decimal('wharfage', 8, 2)->default(0.00);
            $table->integer('penalty_days')->default(0);
            $table->decimal('storage', 8, 2)->default(0.00);
            $table->decimal('electricity', 8, 2)->default(0.00);
            $table->decimal('destuffing', 8, 2)->default(0.00);
            $table->decimal('lifting', 8, 2)->default(0.00);
            $table->timestamps();




        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cargos');
    }
};
