# Validação da entrega 0.3

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
