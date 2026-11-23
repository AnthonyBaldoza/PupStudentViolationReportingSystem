<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('violation_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('path');            // private disk path (random name)
            $table->string('original_name');   // display only, always escaped in views
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->string('sha256', 64);
            $table->timestamps();

            $table->index('violation_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_files');
    }
};
