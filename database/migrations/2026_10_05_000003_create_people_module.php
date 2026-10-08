<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['students', 'guardians'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id();
                $t->string('name', 150);
                $t->text('cpf')->nullable();
                $t->string('cpf_hash', 64)->nullable()->unique();
                $t->date('birth_date')->nullable();
                if ($table === 'guardians') {
                    $t->string('phone', 20)->nullable();
                    $t->string('email')->nullable();
                }
                $t->boolean('active')->default(true);
                $t->timestamps();
                $t->index(['active', 'name']);
            });
        }
        Schema::create('guardian_student_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->restrictOnDelete();
            $t->foreignId('guardian_id')->constrained()->restrictOnDelete();
            $t->string('relationship', 30);
            $t->boolean('is_primary')->default(false);
            $t->boolean('can_request')->default(false);
            $t->timestamp('started_at', 6);
            $t->timestamp('ended_at', 6)->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('supersedes_id')->nullable()->constrained('guardian_student_links')->restrictOnDelete();
            $t->text('reason');
            $t->text('end_reason')->nullable();
            $t->timestamps(6);
            $t->index(['student_id', 'ended_at']);
            $t->index(['guardian_id', 'ended_at']);
        });
        // Uniqueness constraints are installed by the incremental 000005 migration,
        // using generated columns on MySQL and partial indexes on SQLite/PostgreSQL.
        Schema::create('person_revisions', function (Blueprint $t) {
            $t->id();
            $t->string('person_type', 20);
            $t->unsignedBigInteger('person_id');
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->text('changes');
            $t->text('reason')->nullable();
            $t->timestamp('created_at');
            $t->index(['person_type', 'person_id', 'id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('person_revisions');
        Schema::dropIfExists('guardian_student_links');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('students');
    }
};
