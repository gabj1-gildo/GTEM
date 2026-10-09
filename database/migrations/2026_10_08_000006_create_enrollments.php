<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->restrictOnDelete();
            $t->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $t->string('status', 20)->default('RASCUNHO');
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->string('closure_type', 20)->nullable();
            $t->date('closed_on')->nullable();
            $t->timestamps();
            $t->unique(['student_id', 'academic_year_id']);
        });
        Schema::create('enrollment_periods', function (Blueprint $t) {
            $t->id(); $t->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $t->foreignId('guardian_id')->constrained()->restrictOnDelete();
            $t->foreignId('school_id')->constrained()->restrictOnDelete();
            $t->foreignId('grade_id')->constrained()->restrictOnDelete();
            $t->foreignId('shift_id')->constrained()->restrictOnDelete();
            $t->date('starts_on'); $t->date('ends_on'); $t->date('original_ends_on');
            $t->timestamp('cancelled_at')->nullable();
            $t->string('term_path')->nullable(); $t->string('term_sha256', 64)->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps(); $t->index(['starts_on', 'ends_on']);
        });
        Schema::create('enrollment_period_operation', function (Blueprint $t) {
            $t->foreignId('enrollment_period_id')->constrained()->restrictOnDelete();
            $t->foreignId('line_operation_id')->constrained()->restrictOnDelete();
            $t->primary(['enrollment_period_id', 'line_operation_id'], 'enrollment_operation_primary');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('enrollment_period_operation');
        Schema::dropIfExists('enrollment_periods');
        Schema::dropIfExists('enrollments');
    }
};
