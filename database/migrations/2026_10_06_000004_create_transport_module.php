<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transport_locks',function(Blueprint $t){ $t->unsignedInteger('id')->primary(); });
        DB::table('transport_locks')->insert(['id'=>1]);
        Schema::create('route_lines',function(Blueprint $t){
            $t->id(); $t->string('code',30)->unique(); $t->string('name',150);
            $t->text('description')->nullable(); $t->boolean('active')->default(true);
            $t->unsignedInteger('version')->default(1); $t->timestamps();
        });
        Schema::create('vehicles',function(Blueprint $t){
            $t->id(); $t->string('plate',7)->unique(); $t->string('prefix',30)->nullable();
            $t->string('manufacturer',80)->nullable(); $t->string('model',100);
            $t->unsignedSmallInteger('manufacture_year')->nullable(); $t->string('kind',20);
            $t->unsignedSmallInteger('capacity'); $t->string('ownership',20); $t->string('status',30);
            $t->unsignedInteger('version')->default(1); $t->timestamps();
        });
        Schema::create('line_operations',function(Blueprint $t){
            $t->id(); $t->foreignId('route_line_id')->constrained()->restrictOnDelete();
            $t->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $t->foreignId('shift_id')->constrained()->restrictOnDelete();
            $t->string('name',100); $t->date('starts_on'); $t->date('ends_on');
            $t->time('starts_at'); $t->time('ends_at'); $t->json('weekdays');
            $t->string('status',20)->default('ATIVA'); $t->unsignedInteger('version')->default(1); $t->timestamps();
            $t->index(['route_line_id','academic_year_id','shift_id']);
        });
        Schema::create('vehicle_allocations',function(Blueprint $t){
            $t->id(); $t->foreignId('line_operation_id')->constrained()->restrictOnDelete();
            $t->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $t->date('starts_on'); $t->date('ends_on'); $t->date('original_ends_on');
            $t->unsignedSmallInteger('capacity_snapshot'); $t->text('reason');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('cancelled_at')->nullable(); $t->timestamp('released_at')->nullable();
            $t->foreignId('released_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('release_reason')->nullable();
            $t->unsignedInteger('version')->default(1); $t->timestamps();
            $t->index(['line_operation_id','starts_on','ends_on']); $t->index(['vehicle_id','starts_on','ends_on']);
        });
        Schema::create('transport_revisions',function(Blueprint $t){
            $t->id(); $t->string('entity',40); $t->unsignedBigInteger('entity_id');
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->json('before'); $t->json('after'); $t->text('reason')->nullable(); $t->timestamp('created_at');
            $t->index(['entity','entity_id','id']);
        });
    }
    public function down(): void
    {
        foreach (['transport_revisions','vehicle_allocations','line_operations','vehicles','route_lines','transport_locks'] as $table) Schema::dropIfExists($table);
    }
};
