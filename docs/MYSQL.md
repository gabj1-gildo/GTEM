# MySQL — configuração e migração

Desde a versão 0.3.1, o GTEM adota MySQL 8.0+ com InnoDB e `utf8mb4`. O SQLite permanece disponível para os testes rápidos e como origem de importação. A conexão PostgreSQL foi conservada para não interromper instalações anteriores, mas não é o destino previsto nem integra a matriz atual de CI.

## Configuração

Crie um banco vazio e configure no `.env`: `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD`. Para o Laragon local, o host normalmente é `127.0.0.1` e a porta `3306`; use as credenciais da sua instalação. Credenciais de um servidor externo não devem ser copiadas para `.env.example`, commits ou documentação.

Para um servidor configurado para TLS, `MYSQL_ATTR_SSL_CA` aceita o caminho do certificado da autoridade certificadora. O cliente não desabilita a verificação do certificado. A comunicação externa e as exigências de TLS devem ser conferidas com o administrador do servidor.

Criação do banco, por uma conta autorizada:

```sql
CREATE DATABASE gtem_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

O usuário do GTEM precisa das permissões de dados e de esquema necessárias às migrations nesse banco. A aplicação não cria automaticamente bancos ou contas de MySQL.

## Instalação nova

Configure o `.env` e execute:

```powershell
php artisan key:generate
php artisan migrate --seed
php artisan gtem:admin
```

Gere a chave somente para uma instalação nova, sem dados criptografados anteriores.

## Migrar uma instalação SQLite existente

1. Faça backup consistente do SQLite e guarde também o `.env` original em local protegido.
2. Interrompa novas gravações na origem: coloque a aplicação em manutenção e pare eventuais workers antes da cópia definitiva.
3. Configure o destino MySQL, preservando exatamente a `APP_KEY` original. O destino deve estar vazio.
4. Execute as migrations **sem `--seed`**, confira a origem e importe:

```powershell
php artisan config:clear
php artisan migrate
php artisan gtem:import-sqlite database/database.sqlite --dry-run
php artisan gtem:import-sqlite database/database.sqlite
php artisan db:seed
php artisan view:clear
```

5. Confira login, cadastros, vínculos e operações. Se a aplicação foi colocada em manutenção, execute `php artisan up` após a conferência.

Não recrie o administrador no destino antes da importação: a conta e a senha existentes serão copiadas. Não use `migrate:fresh` em um banco de trabalho. Se o destino já contiver dados, pare e escolha um banco vazio; o importador não combina bases nem apaga registros.

### O que o importador confere

- O arquivo é um SQLite válido, legível e com integridade de dados e referências.
- O esquema corresponde aos módulos existentes do GTEM e o destino já recebeu as migrations.
- As tabelas de dados do destino estão vazias. `migrations` e a linha de bloqueio do transporte são gerenciadas no destino.
- A chave atual consegue ler CPFs e histórico criptografado da origem; as impressões digitais dos CPFs são compatíveis.
- IDs, senhas, campos criptografados, vínculos, revisões, auditoria, sessões e filas são copiados sem recriptografia ou novos hashes.
- Valores DATE que o SQLite armazenou com sufixo de horário são normalizados para datas. A conferência de JSON considera valores e estrutura, sem depender da ordem das propriedades.
- Uma conferência de conteúdo compara todas as linhas copiadas; erro de integridade ou diferença reverte toda a transação de dados.

A origem é aberta somente para leitura e permanece preservada. O modo `--dry-run` não insere dados. A transação mantém as chaves estrangeiras habilitadas e registra uma ocorrência de auditoria ao terminar. O esquema previamente criado pelas migrations permanece, mesmo se a importação dos dados for revertida.

O importador atende o esquema da versão 0.3/0.3.1 e carrega os dados em memória para a conferência. Bases grandes ou com módulos adicionais exigem planejamento específico. Faça a importação definitiva sem usuários ou workers gravando na origem ou no destino.

## Unicidade dos vínculos

A migration incremental `2026_10_08_000005_enforce_guardian_link_uniqueness.php` instala índices únicos que impedem dois vínculos abertos para o mesmo aluno/responsável e dois responsáveis principais abertos para um aluno. Versões encerradas continuam permitidas.

No MySQL, os índices usam marcadores gerados a partir da vigência e do indicador de principal. Isso mantém a proteção no banco, além da validação PHP. Em SQLite/PostgreSQL, os índices parciais anteriores são preservados ou criados se ainda não existirem.

Referência técnica: [MySQL — índices em colunas geradas](https://dev.mysql.com/doc/refman/8.0/en/create-table-secondary-indexes.html).

## Testes isolados

Crie um banco exclusivo chamado `gtem_test`. A suíte usa `RefreshDatabase` e pode recriar suas tabelas; nunca aponte os testes para o banco de trabalho.

No PowerShell, definindo as credenciais do banco de testes:

```powershell
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_DATABASE = 'gtem_test'
$env:DB_USERNAME = 'root'
$env:DB_PASSWORD = ''
php vendor/phpunit/phpunit/phpunit --testdox
```

Use outro terminal para iniciar a aplicação depois dos testes, pois essas variáveis valem para a sessão atual. A classe base resolve também `DB_URL` e recusa bancos diferentes de `:memory:` e `gtem_test` antes de executar migrations.

Sem as variáveis acima, `phpunit.xml` seleciona SQLite em memória. Os cinco testes específicos do importador exigem MySQL e são pulados nesse modo. O GitHub Actions executa a matriz SQLite/MySQL.
