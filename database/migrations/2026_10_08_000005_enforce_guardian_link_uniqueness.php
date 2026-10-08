<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName()==='mysql') {
            Schema::table('guardian_student_links',function(Blueprint $table) {
                // Multiple NULLs are allowed; only current links participate in uniqueness.
                $table->unsignedTinyInteger('open_pair_marker')->nullable()->virtualAs('CASE WHEN ended_at IS NULL THEN 1 ELSE NULL END');
                $table->unsignedTinyInteger('open_primary_marker')->nullable()->virtualAs('CASE WHEN ended_at IS NULL AND is_primary = 1 THEN 1 ELSE NULL END');
                $table->unique(['student_id','guardian_id','open_pair_marker'],'links_open_pair_unique');
                $table->unique(['student_id','open_primary_marker'],'links_open_primary_unique');
            });
            return;
        }
        // Existing SQLite/PostgreSQL installations may already have these indexes.
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS links_open_pair_unique ON guardian_student_links (student_id, guardian_id) WHERE ended_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS links_open_primary_unique ON guardian_student_links (student_id) WHERE ended_at IS NULL AND is_primary = true');
    }
    public function down(): void
    {
        if (DB::getDriverName()==='mysql') {
            Schema::table('guardian_student_links',function(Blueprint $table) {
                $table->dropUnique('links_open_pair_unique'); $table->dropUnique('links_open_primary_unique');
                $table->dropColumn(['open_pair_marker','open_primary_marker']);
            });
        }
        // On SQLite/PostgreSQL, keep pre-existing protections until their table is dropped.
    }
};
