# GTEM — Gestão do Transporte Escolar Municipal

Sistema para apoiar a Secretaria Municipal de Transporte Escolar no cadastro de alunos e responsáveis, no planejamento das linhas e na gestão da frota.

O projeto também prevê matrícula e rematrícula online: o responsável deverá baixar um termo em PDF, assiná-lo externamente pelo GOV.BR e anexá-lo para análise de um servidor público.

**Versão atual: 0.4 - Matrículas anuais e vagas, em desenvolvimento.** A área administrativa inclui pessoas, transporte e matrículas com aprovação, termo privado, transferências e ocupação. O portal do responsável e a geração do termo serão desenvolvidos na próxima etapa.

## Funcionalidades disponíveis

| Módulo | Recursos |
|---|---|
| Acesso administrativo | Login, recuperação de senha, usuários, perfis e permissões |
| Base escolar | Anos letivos, períodos de inscrição, escolas, séries, turnos, localidades e ofertas escolares |
| Alunos e responsáveis | Cadastro, pesquisa, inativação, CPF criptografado e histórico de alterações |
| Vínculos familiares | Responsável principal, autorização para solicitar transporte e histórico das vigências |
| Linhas e operações | Trajetos permanentes e atendimento por ano, turno, dias, horários e período |
| Frota | Veículos, capacidade para alunos e situação operacional |
| Alocações | Designação e substituição de veículos, bloqueio de conflitos e capacidade prevista por data |
| Matrículas anuais | Rascunhos, termo assinado, aprovação administrativa, transferências e término com histórico |
| Vagas | Ocupação por data, verificação de todo o período e substituição de veículo compatível com os alunos |
| Auditoria | Autor, data, justificativa e histórico das ações administrativas |

O cadastro de um aluno e o rascunho administrativo não representam uma matrícula efetiva. A aprovação exige termo anexado, conferência humana e vagas em todo o período. Cada operação informa capacidade, ocupação e vagas previstas por data.

## Tecnologias

- PHP 8.4 e Laravel 12.
- Livewire 4, com Alpine integrado.
- Tailwind CSS e folhas de estilo locais.
- MySQL 8.0 ou superior com InnoDB e `utf8mb4` como banco principal; SQLite para testes rápidos e importação da instalação anterior.
- PHPUnit 11 e workflow de testes no GitHub Actions.

## Executar localmente

Requisitos: Git, MySQL 8.0+, PHP 8.4, Composer 2 e as extensões exigidas pelo Laravel, incluindo `pdo_mysql`. Os testes e a importação também usam `pdo_sqlite`. No Windows, os comandos abaixo podem ser executados pelo terminal do Laragon com PHP e Composer disponíveis no `PATH`.

### 1. Obter o código e instalar dependências

```powershell
git clone https://github.com/gabj1-gildo/GTEM.git
cd GTEM
composer install
Copy-Item .env.example .env
```

### 2. Configurar o MySQL

Crie um banco vazio pelo gerenciador do MySQL, se ele ainda não existir:

```sql
CREATE DATABASE gtem_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

No arquivo `.env`, ajuste:

```dotenv
APP_ENV=local
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gtem_db
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=database
```

O exemplo usa a instalação local do Laragon. Para outro servidor, informe o host, a porta, o usuário e a senha fornecidos pelo administrador desse banco.

### 3. Preparar e iniciar a aplicação

Estes comandos são destinados a uma instalação nova:

```powershell
php artisan key:generate
php artisan migrate --seed
php artisan gtem:admin
php artisan serve --host=127.0.0.1 --port=8000
```

Abra [http://127.0.0.1:8000/entrar](http://127.0.0.1:8000/entrar). O comando `gtem:admin` solicita os dados da conta administrativa; o repositório não fornece uma senha padrão.

Os arquivos CSS compilados estão versionados, portanto não é necessário executar npm para iniciar a aplicação. Para alterar e recompilar o estilo principal, use `npm install` e `npm run build`.

Em uma instalação existente, preserve o banco e a `APP_KEY`. Não execute `key:generate` novamente nem `migrate:fresh`: a chave existente é necessária para ler os dados criptografados. Para trocar SQLite por MySQL, siga o [guia de migração](docs/MYSQL.md) antes de executar o seeder ou cadastrar usuários no destino.

## Primeiro uso

1. Configure o ano letivo e os cadastros escolares.
2. Cadastre responsáveis, alunos e seus vínculos.
3. Cadastre as linhas e os veículos.
4. Crie as operações, indicando ano, turno, dias, horários e vigência.
5. Abra cada operação para alocar um veículo e consultar a capacidade prevista.
6. Em **Matrículas anuais**, prepare o rascunho, anexe o termo assinado e solicite a conferência de um servidor autorizado.

Para atualizar uma instalação existente para a etapa 4, execute `php artisan migrate`, `php artisan db:seed` e `php artisan view:clear`. Veja o [fluxo e as regras de matrícula](docs/ETAPA_04.md).

Os perfis iniciais são Administrador, Gestor, Operador e Consulta/Auditoria. As permissões são verificadas no servidor e podem ser ajustadas pela administração.

## Testes

```powershell
php vendor/phpunit/phpunit/phpunit --testdox
```

O comando acima usa SQLite em memória, sem alterar o banco da aplicação. Os testes específicos de importação são executados quando o destino é MySQL. A validação da versão 0.4 no MySQL 8.0.30 aprovou **85 testes e 367 verificações**.

O workflow em [`.github/workflows/tests.yml`](.github/workflows/tests.yml) executa com SQLite e MySQL 8.0. Veja [como testar com MySQL](docs/MYSQL.md) e os [resultados da validação](docs/VALIDACAO.md).

## Próximas etapas

- [x] Matrículas anuais administrativas, ocupação e controle de vagas.
- [ ] Portal do responsável para primeira matrícula e rematrícula.
- [ ] Geração do termo PDF e envio pelo portal da versão assinada pelo GOV.BR (o anexo administrativo já está disponível).
- [ ] Fila de solicitações, recusa, complementação documental e lista de espera.
- [ ] Motoristas, documentação e conformidade da frota.
- [ ] Histórico e programação de manutenções.
- [ ] Relatórios e acompanhamento da operação diária.

A assinatura será realizada fora do sistema; não há integração direta com o GOV.BR nesta versão. As regras institucionais de elegibilidade e os documentos obrigatórios ainda precisam ser definidos.

## Estrutura do projeto

| Diretório | Conteúdo |
|---|---|
| `app/Livewire` | Telas e ações da área administrativa |
| `app/Models` | Entidades e relacionamentos |
| `app/Services` | Regras de negócio, transações e auditoria |
| `database/migrations` | Evolução incremental do banco |
| `database/seeders` | Perfis e permissões iniciais |
| `resources/views` | Templates da interface |
| `public` | Entrada web e arquivos estáticos |
| `tests` | Testes automatizados |
| `docs` | Guias, decisões e validações |

## Documentação

- [Guia de execução, atualização e uso](docs/GUIA_LOCAL.md).
- [MySQL: configuração, importação do SQLite e testes](docs/MYSQL.md).
- [Etapa 02 — alunos e responsáveis](docs/ETAPA_02.md).
- [Etapa 03 — linhas, frota e operações](docs/ETAPA_03.md).
- [Etapa 04 - matrículas anuais, termos e vagas](docs/ETAPA_04.md).
- [Resultados e limites da validação](docs/VALIDACAO.md).

O `.gitignore` mantém fora do versionamento o ambiente local, bancos, uploads, logs, caches, credenciais e dependências instaladas. Os dados pessoais usados localmente não fazem parte deste repositório.
