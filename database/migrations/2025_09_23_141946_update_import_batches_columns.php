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
        Schema::table('import_batches', function (Blueprint $table) {
            $table->decimal('progress', 5, 2)->default(0.00)->change();
            $table->integer('error_count')->default(0)->change();
            $table->text('validation_errors')->nullable()->change();
            $table->text('message')->nullable()->change();
            $table->timestamp('started_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            // This down method will attempt to revert the changes.
            // Note: The specific types here depend on their original definitions.
            $table->decimal('progress', 5, 2)->change();
            $table->integer('error_count')->change();
            $table->text('validation_errors')->change();
            $table->text('message')->change();
            $table->timestamp('started_at')->change();
        });
    }
};