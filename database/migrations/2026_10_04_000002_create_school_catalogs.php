<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $t) {
            $t->id(); $t->unsignedSmallInteger('year')->unique(); $t->date('starts_on'); $t->date('ends_on');
            $t->string('status', 20)->default('PLANEJAMENTO'); $t->timestamps();
        });
        Schema::create('registration_periods', function (Blueprint $t) {
            $t->id(); $t->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $t->string('type', 30); $t->date('starts_on'); $t->date('ends_on'); $t->boolean('active')->default(true);
            $t->timestamps(); $t->unique(['academic_year_id', 'type']);
        });
        Schema::create('schools', function (Blueprint $t) {
            $t->id(); $t->string('name', 150); $t->string('network', 30); $t->string('address', 255)->nullable();
            $t->string('phone', 30)->nullable(); $t->boolean('active')->default(true); $t->timestamps();
        });
        foreach (['grades', 'shifts'] as $name) Schema::create($name, function (Blueprint $t) {
            $t->id(); $t->string('name', 100); $t->string('code', 30)->unique(); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('localities', function (Blueprint $t) {
            $t->id(); $t->string('name', 150); $t->string('type', 30); $t->boolean('active')->default(true); $t->timestamps();
        });
        Schema::create('school_offerings', function (Blueprint $t) {
            $t->id(); $t->foreignId('school_id')->constrained()->restrictOnDelete();
            $t->foreignId('grade_id')->constrained()->restrictOnDelete(); $t->foreignId('shift_id')->constrained()->restrictOnDelete();
            $t->boolean('active')->default(true); $t->timestamps(); $t->unique(['school_id','grade_id','shift_id']);
        });
    }
    public function down(): void
    {
        foreach (['school_offerings','localities','shifts','grades','schools','registration_periods','academic_years'] as $table) Schema::dropIfExists($table);
    }
};
