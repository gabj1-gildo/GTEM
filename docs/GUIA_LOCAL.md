# GTEM — Gestão do Transporte Escolar Municipal

Versão de desenvolvimento **0.3.1 - Linhas, frota e operações com MySQL**.

Aplicação administrativa baseada no planejamento consolidado v2. Esta versão entrega a fundação, o cadastro de pessoas e o planejamento de transporte; o ciclo de matrícula digital ainda não está implementado.

## Implementado

- Login, logout, recuperação de senha e contas administrativas.
- Perfis Administrador, Gestor, Operador e Consulta/Auditoria.
- Permissões verificadas no servidor, inclusive nas ações Livewire.
- Auditoria administrativa e cadastros escolares básicos.
- Anos letivos, períodos de inscrição, escolas, séries, turnos, localidades e ofertas escolares.
- Alunos e responsáveis: criação, pesquisa, edição e inativação.
- CPF opcional, com verificação de dígitos e bloqueio de duplicidade quando informado.
- CPF criptografado no banco e mascarado nas listagens e no histórico visual.
- Ficha individual e histórico cadastral com versões criptografadas.
- Vínculos entre responsáveis e alunos, responsável principal e autorização para solicitar transporte.
- Versionamento de alterações dos vínculos, encerramento com motivo e consulta das vigências anteriores.
- Uma única relação vigente por par aluno/responsável e um único responsável principal vigente por aluno.
- Proteção contra edição de cadastro desatualizado.
- Linhas permanentes e operações por ano, turno, dias da semana, horários e vigência.
- Veículos com placa, capacidade para alunos, propriedade e situação operacional.
- Alocações por período, bloqueio de conflitos de horário e liberação para substituição.
- Capacidade prevista por data, com valor preservado no histórico da alocação.
- Histórico das alterações e permissões específicas para frota e alocações.

O cadastro ativo de um aluno **não é uma matrícula de transporte**.

## Tecnologias

- PHP 8.4 e Laravel 12.
- Livewire 4, com Alpine fornecido pelo próprio Livewire.
- Tailwind CSS e folhas de estilo locais.
- MySQL 8.0+ como banco principal; SQLite para testes rápidos e leitura da instalação anterior.
- PHPUnit 11.

O planejamento sugeria Livewire 3. A fundação adotou Livewire 4. As versões PHP estão fixadas em `composer.lock`. Os assets já compilados acompanham o código.

## Executar uma cópia nova

Requisitos: MySQL 8.0+, PHP 8.4, Composer 2 e extensões usuais do Laravel, incluindo `pdo_mysql`. Instale também `pdo_sqlite` para os testes rápidos e a importação da instalação anterior.

Na pasta da aplicação:

```powershell
composer install
Copy-Item .env.example .env
```

Crie um banco MySQL vazio chamado `gtem_db`, com charset `utf8mb4` e collation `utf8mb4_unicode_ci`. Configure o arquivo de ambiente; para o MySQL local do Laragon:

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

Em uma instalação nova:

```powershell
php artisan key:generate
php artisan migrate --seed
php artisan gtem:admin
php artisan serve --host=127.0.0.1 --port=8000
```

O comando `gtem:admin` solicita nome, e-mail e senha. Não existe senha padrão no pacote. Abra **http://127.0.0.1:8000/entrar**.

Apenas em uma instalação nova gere `APP_KEY`. Em uma instalação que já possui dados, preserve a chave existente: ela protege o CPF, as versões cadastrais e outros dados da aplicação.

Para criar os cadastros escolares fictícios opcionais:

```powershell
php artisan gtem:demo
```

Esse comando não cria usuários, alunos ou responsáveis.

## Atualizar uma instalação existente

Faça backup do banco e guarde a chave da aplicação separadamente, com acesso restrito. Preserve o arquivo de ambiente e os arquivos locais de dados. Se a instalação anterior usa SQLite, siga primeiro [MYSQL.md](MYSQL.md); a importação precisa de um destino vazio, antes do seeder.

```powershell
composer install
php artisan migrate
php artisan db:seed
php artisan view:clear
```

As migrations adicionam as novas tabelas. O seeder concede permissões novas conforme o perfil inicial, uma única vez; executar novamente não restaura permissões que foram revogadas de perfis não administrativos.

Não use `migrate:fresh` no banco da aplicação.

## Usar o módulo de pessoas

1. Abra **Responsáveis** e cadastre os dados de contato.
2. Abra **Alunos** e cadastre nome, nascimento e CPF, se informado.
3. Abra a ficha do aluno e selecione **Vincular responsável**.
4. Escolha o responsável, o tipo de vínculo e, se pertinente, marque responsável principal.
5. Marque a autorização para solicitar transporte somente após a conferência administrativa e registre a justificativa.
6. Consulte **Vínculos vigentes**, **Histórico de vínculos** e **Alterações cadastrais** na ficha.

Alterar um vínculo encerra a versão anterior e cria outra, sem apagar a anterior. Alterações em dados pessoais exigem justificativa. Desativar a pessoa preserva vínculos e histórico, mas impede a autorização enquanto o cadastro estiver inativo.

Reativar uma pessoa volta a tornar utilizáveis autorizações que continuem vigentes. Para revogação permanente, encerre ou altere o vínculo.

O CPF é opcional nesta etapa porque a lista institucional de documentos obrigatórios ainda está pendente. A validação é matemática; não consulta a Receita Federal nem comprova identidade ou vínculo legal.

## Usar o módulo de transporte

1. Em **Linhas**, cadastre o código, nome e descrição do trajeto.
2. Em **Veículos**, registre placa, modelo, capacidade para alunos e situação operacional.
3. Em **Operações das linhas**, escolha linha, ano letivo e turno; informe vigência, dias e horário de atendimento.
4. Clique em **Abrir operação** e aloque um veículo no período desejado, com justificativa.
5. Consulte a capacidade prevista selecionando uma data.
6. Para substituir o veículo, clique em **Liberar / substituir**, informe o primeiro dia sem o veículo atual e o motivo. Depois aloque o substituto a partir dessa data.

Os períodos incluem as datas inicial e final. Horários contíguos são permitidos; horários sobrepostos com um dia de atendimento em comum são bloqueados. Não há cálculo de deslocamento entre linhas.

Operações com histórico de alocação conservam linha, ano, turno, vigência, dias e horários. Uma mudança de programação requer nova operação. Um veículo já utilizado conserva sua placa; a capacidade só pode mudar depois de liberar alocações vigentes e futuras.

Marcar um veículo como indisponível ou em manutenção mantém as alocações e sinaliza a necessidade de substituição. A capacidade mostrada é a do planejamento registrado; não é um total de vagas livres. Consulte `docs/ETAPA_03.md` para as regras e limites.

## Testes

```powershell
php vendor/phpunit/phpunit/phpunit --testdox
```

Os testes usam um banco SQLite em memória e não alteram o banco local. A classe base recusa bancos fora de `:memory:` e `gtem_test`.

Cenários incluem duplicidade de CPF, datas, criptografia, autorização, perfis de consulta, bloqueio de edição concorrente, duplicidade de vínculos, responsável principal, encerramento e preservação de histórico.

Veja `docs/VALIDACAO.md` para os resultados desta entrega e `docs/MYSQL.md` para executar a suíte com MySQL. A validação local não substitui a conferência da conexão e das permissões do servidor de implantação.

## Estilos

Os arquivos `public/app.css`, `public/people.css` e `public/transport.css` já acompanham a aplicação. Não é necessário executar npm para abrir a versão entregue.

Para alterar e recompilar o estilo principal:

```powershell
npm install
npm run build
```

O arquivo `public/people.css` é uma folha de estilo complementar mantida diretamente como código-fonte. A aplicação não carrega CSS ou JavaScript por CDN.

## Recuperação de conta

A configuração local usa `MAIL_MAILER=log`: as mensagens de recuperação são registradas no log, sem envio externo. Antes de uso real, configure o serviço SMTP, domínio e demais parâmetros de implantação.

## Próximas etapas

- Matrículas anuais, ocupação e controle de vagas das operações.
- Motoristas, documentação e conformidade da frota.
- Portal do responsável, documentos e solicitação de matrícula.
- PDF do termo, upload da versão assinada externamente no GOV.BR e análise humana.
- Aprovação, matrícula, lista de espera, manutenção e operação diária.

Não estão implementadas nesta versão integrações GOV.BR, assinatura automática, validação documental, notificações externas ou matrícula efetiva.

## Organização do código

- `app/Services/PeopleService.php`: validação e gravação de pessoas, CPF e revisões.
- `app/Services/GuardianLinkService.php`: regras e transações dos vínculos.
- `app/Models/`: entidades e relacionamentos.
- `app/Livewire/People.php` e `PersonDetail.php`: telas administrativas.
- `database/migrations/`: esquema incremental.
- `tests/Feature/`: testes dos fluxos e controles de acesso.
- `docs/ETAPA_02.md`: decisões de implementação e rastreabilidade.
- `app/Services/TransportService.php`: validação e histórico dos cadastros de transporte.
- `app/Services/AllocationService.php`: alocações e liberações transacionais.
- `app/Services/TransportSchedule.php`: conflitos de programação e serialização das gravações.
- `app/Services/OperationCapacity.php`: consulta da capacidade prevista por data.
- `docs/ETAPA_03.md`: regras de linhas, frota e operações.
