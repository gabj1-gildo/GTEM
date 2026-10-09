# Validação da entrega 0.4 — Matrículas anuais

Execução local em 08/10/2026, horário de Brasília. A migração anterior para MySQL foi confirmada pelo usuário. Esta entrega adiciona o módulo administrativo de matrículas.

- MySQL **8.0.30**, PHP **8.4.15**: **85 testes aprovados, 367 assertions**, sem falhas.
- SQLite em memória: **80 testes aprovados, 348 assertions e 5 testes específicos de MySQL pulados**, sem falhas.
- **22 cenários novos** de matrículas: rascunhos sem reserva; termo e conferência obrigatórios; unicidade aluno/ano no serviço e no banco; lotação futura; compartilhamento de vaga em períodos distintos; lacunas em dias úteis e finais de semana; oferta escolar e responsável autorizado; revogação antes da aprovação; transferências e reversão; término com vigência; substituição atômica por veículo suficiente; versões desatualizadas; tipo/integridade de PDF; acesso privado e escopo de download; restrições de perfis; fluxo Livewire de criação, upload e aprovação; tentativa de transferência corrigida com o mesmo upload; ida e volta; proteção de referências históricas.
- Os testes anteriores de autenticação, pessoas, vínculos, transporte e importação também passaram.
- Migration incremental e seeder aplicados a uma **cópia MySQL isolada com os dados anteriores**, preservando integralmente usuários, senhas, alunos, responsáveis, vínculos, revisões e cadastros escolares por comparação de conteúdo.
- As três novas tabelas foram verificadas e ficaram vazias nessa cópia. O `.env` permaneceu inalterado.
- Sintaxe PHP verificada em **85 arquivos**. `git diff --check` sem erros.
- Rotas, renderização e ações foram exercitadas por testes HTTP/Livewire; não foi realizada uma nova inspeção visual em navegador nem teste de carga com múltiplos processos.

## Aplicação no servidor configurado

A conexão definida no `.env` continua bloqueada pelas permissões de rede desta sessão (SQLSTATE 2002, socket proibido). **A migration 000006 e as novas permissões não foram aplicadas nesse servidor por esta sessão.** Execute os três comandos de atualização em [ETAPA_04.md](ETAPA_04.md) no terminal que já tem acesso ao MySQL. A importação SQLite anterior não precisa ser repetida.

O portal do responsável, a geração do termo e a fila de solicitações ainda não fazem parte desta entrega. A assinatura do PDF é conferida pelo servidor público, sem verificação criptográfica automática. O CI remoto não foi executado por esta sessão.

## Histórico — entrega 0.3.1 - MySQL

Execução local em 08/10/2026. O `.env` já continha a configuração de um servidor MySQL externo; seus valores, credenciais e `APP_KEY` foram preservados.

- MySQL **8.0.30**, InnoDB, PHP 8.4.15: **63 testes aprovados e 267 assertions**, sem falhas.
- SQLite em memória: **58 testes aprovados, 248 assertions e 5 testes específicos de MySQL pulados**.
- Todos os cenários anteriores de pessoas, vínculos, linhas, veículos, operações e permissões passaram no MySQL.
- Novos cenários: pesquisa textual e numérica compatível, unicidade de principal no banco, múltiplas versões encerradas e importação transacional.
- O importador foi exercitado com dados de teste para conferir IDs, senhas, criptografia, datas, simulação sem escrita, recusa de destino ocupado, chave incompatível e reversão por falha de vínculo.
- Uma cópia consistente do SQLite existente foi importada e conferida em um banco MySQL local separado: **1 usuário, 2 alunos, 1 responsável e 1 vínculo**, além de cadastros escolares, permissões, revisões, auditoria e sessões.
- A comparação conferiu todo o conteúdo copiado, sem recalcular senhas ou recriptografar os campos. O arquivo SQLite original e o `.env` permaneceram inalterados.
- Backups do SQLite e do ambiente foram mantidos fora do repositório. Dados pessoais e credenciais não integram os commits.

## Pendência de conexão externa

A conexão ao servidor definido no `.env` foi recusada pelas permissões de rede desta sessão (SQLSTATE 2002, socket proibido). Por isso, **não foi aplicada nenhuma migration nem importação naquele servidor**. A aprovação dos testes se refere ao MySQL do Laragon e ao banco separado de validação.

Para concluir no destino externo, execute os passos de [MYSQL.md](MYSQL.md) em um terminal com acesso ao servidor. Verifique se o destino está vazio antes da importação. O `.env` não foi redirecionado ao banco de validação.

O workflow foi atualizado para SQLite e MySQL 8.0. A execução externa de CI e testes de carga/concorrência com múltiplos processos não fazem parte desta validação local.

## Histórico — entrega 0.3

Execução local em 08/10/2026, horário de Brasília.

- **55 testes aprovados, 240 assertions**, sem falhas ou erros.
- Inclui os 28 testes da etapa anterior e 27 cenários de transporte.
- PHP 8.4.15, Laravel 12.36.1, Livewire 4.3.1 e PHPUnit 11.5.43.
- Banco de testes SQLite em memória, isolado dos dados da aplicação.
- Sintaxe PHP verificada em 69 arquivos de aplicação, configuração, migrations, rotas e testes.
- Backup consistente do banco local realizado e verificado antes da migration incremental.
- Migration de transporte e novas permissões aplicadas sem recriar contas ou cadastros.
- Conferência HTTP autenticada: painel, linhas, veículos, operações, alunos e responsáveis retornaram 200; CSS do transporte e JavaScript Livewire referenciado pela página também disponíveis.
- Comparação com o backup confirmou a preservação dos registros de alunos, responsáveis, vínculos, contas e senhas; integridade SQLite confirmada.

## Cobertura de transporte

- Normalização e unicidade da placa e do código de linha; limites de capacidade e ano de fabricação.
- Justificativa, proteção contra formulário desatualizado, revisão e auditoria das alterações.
- Vigência dentro do ano, horário válido, dias semanais e cadastros habilitados.
- Bloqueio de operação duplicada para mesma linha e turno; horários contíguos permitidos.
- Conflitos de veículo em operações diferentes, considerando datas, horários e ocorrência real de dias da semana em comum.
- Datas inicial e final inclusivas, inclusive alocação de um único dia.
- Rejeição de dois veículos no mesmo período de uma operação, alocação retroativa e veículo indisponível.
- Preservação de placa, capacidade histórica e programação da operação após alocações.
- Liberação, cancelamento de planejamento, substituição e preservação do término original.
- Bloqueio de alteração de alocação pertencente a outra operação ou de versão desatualizada.
- Consulta por data com distinção entre capacidade prevista, ausência de veículo, dia sem atendimento e fora da vigência.
- Aviso de indisponibilidade atual sem apagar o planejamento histórico.
- Restrições de edição do ano e turno para preservar as operações vinculadas.
- Fluxos Livewire de cadastro, edição, consulta de histórico, alocação e liberação.
- Perfil de consulta, verificação das permissões no servidor e preservação de revogações na reexecução do seeder.
- Renderização das páginas e regressão dos módulos de pessoas, vínculos e cadastros escolares.

## Limites desta execução

A validação automatizada foi executada em SQLite. PostgreSQL e concorrência com múltiplas conexões ainda precisam ser exercitados antes da implantação. O workflow de CI existente contempla SQLite e PostgreSQL, mas não foi executado externamente nesta entrega.

A tentativa de inspeção visual automatizada no Chrome local foi interrompida pelo fechamento da conexão DevTools (código 1006). A renderização e as ações das telas foram exercitadas pelos testes Livewire; isso não substitui uma revisão visual manual em navegadores e dispositivos.

Os resultados abaixo registram a entrega anterior, mantida como regressão da versão atual.

## Histórico — entrega 0.2

Resultado da execução em 05/10/2026, horário de Brasília (06/10/2026 UTC).

- PHP 8.4.15, Laravel 12.36.1, Livewire 4.3.1 e PHPUnit 11.5.43.
- 28 testes aprovados, 133 assertions, sem falhas.
- Banco isolado SQLite em memória; dados locais não foram usados nos testes.
- Verificação de sintaxe PHP nos arquivos de aplicação, configuração, migrations, rotas e testes.
- Migration incremental aplicada ao banco de demonstração, com backup prévio.
- Permissões iniciais do módulo instaladas sem redefinir credenciais ou cadastros existentes.

## Cobertura exercitada

- Login, logout, negação de login para usuário inativo e renderização das páginas existentes.
- CPF válido, inválido, repetido, normalizado e duplicado; múltiplos cadastros sem CPF.
- Datas de nascimento, contato do responsável e normalização do e-mail.
- Criptografia de CPF e revisões; ausência desses valores na auditoria geral.
- Máscara de CPF nas listas, fichas e histórico; consulta por CPF completo.
- Justificativa de edição, inativação e preservação de versões cadastrais.
- Rejeição de gravação de um formulário desatualizado.
- Campos não permitidos enviados pelo cliente ignorados na persistência.
- Bloqueio de gravação por perfil de consulta e revogação de permissão durante o uso.
- Idempotência do seeder sem restaurar permissão revogada.
- Autorização explícita, vigência e situação atual de aluno e responsável.
- Bloqueio de vínculo repetido e segundo principal; aceitação de vínculo secundário.
- Restrição de unicidade também no banco.
- Alteração de vínculo cria nova versão; a versão anterior permanece consultável.
- Bloqueio de edição de vínculo antigo ou pertencente a outro aluno.
- Encerramento com motivo e possibilidade de novo vínculo posterior.
- Fluxo Livewire de criação, alteração e encerramento de vínculo.

## Limites da validação

Os testes em PostgreSQL não foram executados neste ambiente. Foi adicionado um workflow de CI para SQLite e PostgreSQL, cuja execução depende da publicação do código em um repositório. Não foi realizada uma bateria de concorrência com múltiplas conexões PostgreSQL, teste de carga, auditoria externa de segurança ou validação documental de identidade.

O resultado confirma os cenários automatizados desta etapa; não representa conclusão do sistema inteiro nem prontidão para produção.
