<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t) { $t->id(); $t->string('code')->unique(); $t->string('name'); $t->timestamps(); });
        Schema::create('permissions', function (Blueprint $t) { $t->id(); $t->string('code')->unique(); $t->string('name'); $t->timestamps(); });
        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->restrictOnDelete();
            $t->foreignId('permission_id')->constrained()->restrictOnDelete(); $t->primary(['role_id', 'permission_id']);
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name', 120); $t->string('email')->unique(); $t->timestamp('email_verified_at')->nullable();
            $t->string('password'); $t->foreignId('role_id')->constrained()->restrictOnDelete();
            $t->boolean('active')->default(true); $t->rememberToken(); $t->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $t) { $t->string('email')->primary(); $t->string('token'); $t->timestamp('created_at')->nullable(); });
        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary(); $t->foreignId('user_id')->nullable()->index(); $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable(); $t->longText('payload'); $t->integer('last_activity')->index();
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('action', 80); $t->string('entity', 80); $t->unsignedBigInteger('entity_id');
            $t->json('before'); $t->json('after'); $t->text('reason')->nullable(); $t->timestamp('created_at')->index();
            $t->index(['entity', 'entity_id']);
        });
        Schema::create('jobs', function (Blueprint $t) {
            $t->id(); $t->string('queue')->index(); $t->longText('payload'); $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable(); $t->unsignedInteger('available_at'); $t->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id(); $t->string('uuid')->unique(); $t->text('connection'); $t->text('queue');
            $t->longText('payload'); $t->longText('exception'); $t->timestamp('failed_at')->useCurrent();
        });
    }
    public function down(): void
    {
        foreach (['failed_jobs','jobs','audit_events','sessions','password_reset_tokens','users','permission_role','permissions','roles'] as $table) Schema::dropIfExists($table);
    }
};
