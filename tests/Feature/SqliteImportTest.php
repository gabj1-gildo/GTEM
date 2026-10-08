<?php
namespace Tests\Feature;
use App\Services\SqliteToMysqlImport;
use App\Support\Cpf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Crypt,DB,Hash,Schema};
use PDO;
use RuntimeException;
use Tests\TestCase;

class SqliteImportTest extends TestCase
{
    use RefreshDatabase;
    private ?string $sourcePath=null;
    private PDO $source;
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName()!=='mysql') $this->markTestSkipped('Import destination requires MySQL.');
        $this->sourcePath=tempnam(sys_get_temp_dir(),'gtem-import-');
        $this->source=new PDO('sqlite:'.$this->sourcePath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        foreach (SqliteToMysqlImport::TABLES as $table) {
            $columns=collect(Schema::getColumns($table))->filter(fn($c)=>empty($c['generation']))->map(fn($c)=>'"'.$c['name'].'" TEXT')->join(',');
            $this->source->exec('CREATE TABLE "'.$table.'" ('.$columns.')');
        }
        $this->insert('roles',['id'=>1,'code'=>'administrador','name'=>'Administrador','created_at'=>'2026-10-08 10:00:00','updated_at'=>'2026-10-08 10:00:00']);
        $this->insert('users',['id'=>17,'name'=>'Usuário de teste','email'=>'import@example.test','password'=>Hash::make('SenhaTeste12345'),'role_id'=>1,'active'=>1]);
        $this->insert('students',['id'=>23,'name'=>'Aluno de teste','cpf'=>Crypt::encryptString('52998224725'),'cpf_hash'=>Cpf::fingerprint('52998224725'),'birth_date'=>'2015-04-10 00:00:00','active'=>1]);
        $this->insert('person_revisions',['id'=>9,'person_type'=>'student','person_id'=>23,'actor_id'=>17,'changes'=>Crypt::encryptString('{"name":{"after":"Aluno de teste"}}'),'created_at'=>'2026-10-08 10:00:00']);
        $this->insert('audit_events',['id'=>2,'actor_id'=>17,'entity'=>'students','entity_id'=>23,'action'=>'pessoa_cadastrada','before'=>'[]','after'=>'{"name":"Aluno de teste","active":true}','created_at'=>'2026-10-08 10:00:00']);
    }
    protected function tearDown(): void
    {
        if ($this->sourcePath) { unset($this->source); unlink($this->sourcePath); }
        parent::tearDown();
    }
    private function insert(string $table,array $data): void
    {
        $stmt=$this->source->prepare('INSERT INTO "'.$table.'" ("'.implode('","',array_keys($data)).'") VALUES ('.implode(',',array_fill(0,count($data),'?')).')');
        $stmt->execute(array_values($data));
    }
    public function test_import_preserves_ids_ciphertext_passwords_dates_and_source(): void
    {
        $originalHash=hash_file('sha256',$this->sourcePath);
        $originalPassword=$this->source->query('SELECT password FROM users')->fetchColumn();
        $originalCpf=$this->source->query('SELECT cpf FROM students')->fetchColumn();
        $report=app(SqliteToMysqlImport::class)->run($this->sourcePath);
        $this->assertSame(1,$report['users']);
        $this->assertSame($originalPassword,DB::table('users')->where('id',17)->value('password'));
        $this->assertSame($originalCpf,DB::table('students')->where('id',23)->value('cpf'));
        $this->assertSame('2015-04-10',DB::table('students')->where('id',23)->value('birth_date'));
        $this->assertSame('52998224725',\App\Models\Student::findOrFail(23)->cpf);
        $this->assertDatabaseHas('audit_events',['action'=>'importacao_sqlite']);
        $this->assertSame($originalHash,hash_file('sha256',$this->sourcePath));
    }
    public function test_dry_run_does_not_insert_rows(): void
    {
        $report=app(SqliteToMysqlImport::class)->run($this->sourcePath,true);
        $this->assertSame(1,$report['students']); $this->assertDatabaseCount('users',0); $this->assertDatabaseCount('students',0);
    }
    public function test_existing_destination_is_not_overwritten(): void
    {
        DB::table('roles')->insert(['id'=>99,'code'=>'existing','name'=>'Existing']);
        try { app(SqliteToMysqlImport::class)->run($this->sourcePath); $this->fail('Expected occupied destination rejection'); }
        catch (RuntimeException $e) { $this->assertStringContainsString('já contém dados',$e->getMessage()); }
        $this->assertDatabaseHas('roles',['id'=>99]); $this->assertDatabaseCount('users',0);
    }
    public function test_wrong_key_stops_import_before_copying_rows(): void
    {
        $this->source->exec("UPDATE students SET cpf='invalid-ciphertext'");
        try { app(SqliteToMysqlImport::class)->run($this->sourcePath); $this->fail('Expected invalid key rejection'); }
        catch (RuntimeException $e) { $this->assertStringContainsString('APP_KEY',$e->getMessage()); }
        $this->assertDatabaseCount('roles',0); $this->assertDatabaseCount('users',0);
    }
    public function test_foreign_key_error_rolls_back_previous_inserts(): void
    {
        $this->source->exec('UPDATE users SET role_id=999');
        try { app(SqliteToMysqlImport::class)->run($this->sourcePath); $this->fail('Expected foreign key rejection'); }
        catch (RuntimeException $e) { $this->assertStringContainsString('revertida',$e->getMessage()); }
        $this->assertDatabaseCount('roles',0); $this->assertDatabaseCount('users',0);
    }
}
