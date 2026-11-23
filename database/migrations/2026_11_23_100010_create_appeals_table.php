<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('violation_record_id')->constrained()->restrictOnDelete();
            $table->foreignId('filed_by')->constrained('users')->restrictOnDelete(); // personnel who logged it for the student
            $table->text('statement');
            $table->string('status', 20)->default('pending'); // pending | upheld | overturned
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_remarks')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('violation_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appeals');
    }
};
