<?php
namespace App\Services;

use App\Support\Cpf;
use Illuminate\Support\Facades\{Crypt,DB};
use PDO;
use RuntimeException;
use Throwable;

final class SqliteToMysqlImport
{
    // Parent records precede foreign keys; link versions are copied in ID order.
    public const TABLES = [
        'roles','permissions','permission_role','users','academic_years','registration_periods',
        'schools','grades','shifts','localities','school_offerings','students','guardians',
        'guardian_student_links','person_revisions','route_lines','vehicles','line_operations',
        'vehicle_allocations','transport_revisions','audit_events','password_reset_tokens',
        'sessions','jobs','failed_jobs',
    ];

    public function run(string $file,bool $dryRun=false): array
    {
        if (DB::getDriverName()!=='mysql') throw new RuntimeException('O destino deve usar DB_CONNECTION=mysql.');
        $path=realpath($file);
        if (!$path || !is_file($path) || !is_readable($path)) throw new RuntimeException('Arquivo SQLite não encontrado ou sem acesso de leitura.');
        $handle=fopen($path,'rb'); $header=fread($handle,16); fclose($handle);
        if ($header!=="SQLite format 3\0") throw new RuntimeException('O arquivo não é um banco SQLite válido.');
        $source=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $source->exec('PRAGMA query_only=ON'); $source->beginTransaction();
        $table='preparação';
        try {
            if ($source->query('PRAGMA integrity_check')->fetchColumn()!=='ok' || $source->query('PRAGMA foreign_key_check')->fetch())
                throw new RuntimeException('O SQLite apresenta falhas de integridade. Nenhum dado foi importado.');
            $tables=$source->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
            if (array_diff(self::TABLES,$tables) || array_diff($tables,[...self::TABLES,'migrations','transport_locks']))
                throw new RuntimeException('O esquema de origem não corresponde à versão suportada do GTEM.');
            $report=DB::transaction(function () use ($source,$dryRun,&$table) {
                // Lock also coordinates simultaneous import attempts against this destination.
                TransportSchedule::lock(); $schema=DB::connection()->getSchemaBuilder();
                foreach (['enrollments','enrollment_periods','enrollment_period_operation'] as $newTable) {
                    if ($schema->hasTable($newTable) && DB::table($newTable)->exists())
                        throw new RuntimeException('O destino já contém dados em '.$newTable.'. A importação não sobrescreve cadastros.');
                }
                $report=[]; $copies=[]; $columnTypes=[];
                foreach (self::TABLES as $table) {
                    if (!$schema->hasTable($table)) throw new RuntimeException('Execute php artisan migrate no destino antes de importar.');
                    if (DB::table($table)->exists()) throw new RuntimeException('O destino já contém dados em '.$table.'. A importação não sobrescreve cadastros.');
                    $types=[];
                    foreach ($schema->getColumns($table) as $column) {
                        if (!empty($column['generation'])) continue;
                        $types[$column['name']]=$column['type_name'];
                    }
                    $rows=$source->query('SELECT * FROM "'.$table.'"')->fetchAll(PDO::FETCH_ASSOC);
                    if ($rows && isset($rows[0]['id'])) usort($rows,fn($a,$b)=>$a['id']<=>$b['id']);
                    foreach ($rows as &$row) {
                        foreach ($row as $field=>&$value) {
                            if (!isset($types[$field])) throw new RuntimeException('Colunas incompatíveis em '.$table.'.');
                            // SQLite stores Eloquent DATE casts with a midnight suffix.
                            if ($types[$field]==='date' && $value!==null) $value=substr($value,0,10);
                        }
                        unset($value);
                        if (in_array($table,['students','guardians']) && ($row['cpf'] ?? null)!==null) {
                            try { $cpf=Crypt::decryptString($row['cpf']); }
                            catch (Throwable) { throw new RuntimeException('A APP_KEY atual não consegue ler os dados de origem. Preserve a chave original.'); }
                            if (!hash_equals(Cpf::fingerprint($cpf) ?? '',$row['cpf_hash'] ?? ''))
                                throw new RuntimeException('O CPF de origem não corresponde à APP_KEY atual. Nenhum dado foi importado.');
                        }
                        if ($table==='person_revisions') {
                            try { Crypt::decryptString($row['changes']); }
                            catch (Throwable) { throw new RuntimeException('A APP_KEY atual não consegue ler o histórico de origem. Preserve a chave original.'); }
                        }
                    }
                    unset($row);
                    $report[$table]=count($rows); $copies[$table]=$rows; $columnTypes[$table]=$types;
                }
                if ($dryRun) return $report;
                foreach (self::TABLES as $table) {
                    // Insert complete rows without events, mutators, new hashes or re-encryption.
                    foreach ($copies[$table] as $row) DB::table($table)->insert($row);
                    $actual=DB::table($table)->get()->map(fn($row)=>(array)$row)->all();
                    if ($this->digest($copies[$table],$columnTypes[$table])!==$this->digest($actual,$columnTypes[$table]))
                        throw new RuntimeException('A conferência dos dados falhou em '.$table.'. A transação foi revertida.');
                }
                Audit::record('importacao_sqlite','database',0,[],['tabelas'=>$report],'Importação conferida de SQLite para MySQL.');
                return $report;
            },3);
            $source->rollBack();
            return $report;
        } catch (Throwable $error) {
            if ($source->inTransaction()) $source->rollBack();
            if ($error instanceof RuntimeException && !$error instanceof \PDOException) throw $error;
            // Database exception SQL/bindings can contain private data; do not print them.
            throw new RuntimeException('Não foi possível importar '.$table.'; confira as migrations, a conexão e os vínculos da origem. A transação foi revertida.');
        }
    }

    private function digest(array $rows,array $types): string
    {
        $normalized=[];
        foreach ($rows as $row) {
            $values=[];
            foreach ($types as $field=>$type) {
                $value=$row[$field] ?? null;
                if ($value!==null) {
                    if ($type==='json') $value=$this->canonical(json_decode($value,true,512,JSON_THROW_ON_ERROR));
                    elseif (in_array($type,['timestamp','datetime'])) $value=str_contains((string)$value,'.') ? rtrim(rtrim((string)$value,'0'),'.') : (string)$value;
                    else $value=(string)$value;
                }
                $values[$field]=$value;
            }
            ksort($values); $normalized[]=json_encode($values,JSON_THROW_ON_ERROR);
        }
        sort($normalized,SORT_STRING);
        return hash('sha256',implode("\n",$normalized));
    }
    private function canonical(mixed $value): mixed
    {
        if (is_array($value)) { if (!array_is_list($value)) ksort($value); foreach ($value as &$item) $item=$this->canonical($item); }
        return $value;
    }
}
