<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('violation_records', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->nullable()->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('violation_category_id')->constrained()->restrictOnDelete();
            $table->string('category_name'); // snapshot, so renames never rewrite history
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();

            $table->date('incident_date');
            $table->time('incident_time')->nullable();
            $table->string('location');
            $table->text('description');

            // pending | approved | rejected | returned | voided
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();

            // Filled in ONLY on approval, and snapshotted so later rule changes never alter history.
            $table->unsignedSmallInteger('offense_number')->nullable();
            $table->foreignId('offense_rule_id')->nullable()->constrained('offense_rules')->nullOnDelete();
            $table->string('action_name')->nullable();
            $table->text('action_description')->nullable();
            $table->text('rule_description')->nullable();

            // Set when an appeal is upheld in the student's favour (record is kept, never deleted).
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();

            $table->timestamps();

            $table->index(['student_id', 'violation_category_id', 'status'], 'violation_records_offense_lookup');
            $table->index('status');
            $table->index('incident_date');
            $table->index('reported_by');
        });

        // Database-level guarantee: a student can never have two APPROVED records with the
        // same offense number in the same category (works on PostgreSQL and SQLite).
        DB::statement("CREATE UNIQUE INDEX violation_records_unique_approved_offense ON violation_records (student_id, violation_category_id, offense_number) WHERE status = 'approved'");

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE violation_records ADD CONSTRAINT violation_records_status_check CHECK (status IN ('pending','approved','rejected','returned','voided'))");
            DB::statement("ALTER TABLE violation_records ADD CONSTRAINT violation_records_approved_has_offense CHECK (status <> 'approved' OR offense_number IS NOT NULL)");
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS violation_records_unique_approved_offense');
        Schema::dropIfExists('violation_records');
    }
};
