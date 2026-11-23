<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            // Unique across archived (soft-deleted) rows too, so an ID can never be reused.
            $table->string('student_no', 30)->unique();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('year_level');
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes(); // "archived"

            $table->index(['last_name', 'first_name']);
            $table->index(['course_id', 'year_level']);
            $table->index('status');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE students ADD CONSTRAINT students_status_check CHECK (status IN ('active','inactive','graduated'))");
            DB::statement('ALTER TABLE students ADD CONSTRAINT students_year_level_check CHECK (year_level BETWEEN 1 AND 6)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
