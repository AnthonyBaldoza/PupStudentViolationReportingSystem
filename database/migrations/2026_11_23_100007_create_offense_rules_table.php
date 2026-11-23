<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offense_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('violation_category_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('offense_number');
            // true = "Nth and succeeding offenses" (applies from offense_number onward)
            $table->boolean('is_succeeding')->default(false);
            $table->text('description')->nullable();
            $table->foreignId('disciplinary_action_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['violation_category_id', 'offense_number']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE offense_rules ADD CONSTRAINT offense_rules_number_check CHECK (offense_number >= 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offense_rules');
    }
};
