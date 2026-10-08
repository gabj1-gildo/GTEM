<?php
namespace App\Console\Commands;

use App\Services\SqliteToMysqlImport;
use Illuminate\Console\Command;

class ImportSqlite extends Command
{
    protected $signature='gtem:import-sqlite {arquivo : Caminho do banco SQLite original} {--dry-run : Conferir sem inserir dados}';
    protected $description='Importar o SQLite para um MySQL vazio, preservando IDs, senhas e criptografia';

    public function handle(SqliteToMysqlImport $import): int
    {
        try { $report=$import->run($this->argument('arquivo'),$this->option('dry-run')); }
        catch (\Throwable $error) { $this->error($error->getMessage()); return self::FAILURE; }
        $this->table(['Tabela','Registros'],collect($report)->map(fn($count,$table)=>[$table,$count])->values()->all());
        $this->info($this->option('dry-run') ? 'Conferência concluída. Nenhum registro foi importado.' : 'Importação concluída e conferida. O arquivo SQLite original foi preservado.');
        return self::SUCCESS;
    }
}
