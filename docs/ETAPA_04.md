# Etapa 04 — Matrículas anuais e ocupação

Versão 0.4. Módulo administrativo acessível pelo menu **Matrículas anuais**, em `/matriculas`.

## Atualização da instalação existente

Na pasta da aplicação, com acesso ao MySQL configurado, preserve a configuração e os dados existentes e execute:

```powershell
php artisan migrate
php artisan db:seed
php artisan view:clear
```

Execute cada comando somente após o anterior terminar com sucesso. A migration `2026_10_08_000006_create_enrollments.php` adiciona três tabelas; não recria cadastros ou contas. Não use `migrate:fresh` nem gere uma nova `APP_KEY`.

O seeder instala quatro permissões. Administrador e Gestor recebem aprovação; Operador prepara rascunhos e anexa termos; Consulta/Auditoria consulta fichas sem acesso aos PDFs. Permissões revogadas de perfis não administrativos continuam revogadas ao repetir o seeder. A edição de perfis vincula aprovação à consulta de matrículas e documentos.

## Fluxo administrativo

1. Prepare o aluno ativo, o responsável ativo e o vínculo vigente com autorização para solicitar transporte.
2. Abra o ano letivo e cadastre a oferta ativa de escola, série e turno.
3. Configure as operações e aloque veículos disponíveis para todo o período que será aprovado.
4. Em **Nova matrícula**, selecione aluno, ano, responsável, escola, série, turno, datas e operações (por exemplo, ida e volta). Informe a justificativa e salve o rascunho.
5. Anexe o termo correspondente, assinado externamente pelo responsável no GOV.BR. O limite da aplicação é PDF de 10 MB. Os limites PHP/web do servidor precisam comportar o arquivo (`upload_max_filesize` e `post_max_size`).
6. O servidor autorizado baixa o termo, confere dados, autoria e assinatura externamente, registra o parecer e confirma a conferência. Somente **Aprovar e reservar vagas** efetiva a matrícula.

Os arquivos são privados, têm nomes gerados pelo servidor, não passam por reprocessamento e conservam os bytes recebidos. O SHA-256 confere a integridade na aprovação. O download exige autenticação e permissão, verifica a matrícula à qual o período pertence e registra o acesso. O sistema verifica tipo, cabeçalho e tamanho do PDF; a conferência da assinatura é humana, sem validação criptográfica automática ou integração com GOV.BR.

Editar um rascunho cria outra versão e exige anexar o termo correspondente. Versões e termos anteriores permanecem consultáveis. Rascunhos não reservam lugares.

## Matrícula e histórico

- `enrollments` identifica aluno e ano letivo, com unicidade no banco, versão para evitar alterações sobre dados desatualizados, aprovação e eventual término.
- `enrollment_periods` registra responsável, escola, série, turno, datas, término original, termo e conferência por período. As versões anteriores são preservadas.
- `enrollment_period_operation` relaciona o período às operações utilizadas pelo aluno.
- Os eventos administrativos registram autor, data, justificativa, IDs dos períodos envolvidos e versão da matrícula. Não registram o conteúdo do termo nem seu caminho privado.

Uma matrícula aprovada não pode ser editada como rascunho. Para alterar escola, série, turno, responsável ou operações, use **Transferir atendimento**. Informe o primeiro dia do novo atendimento, os novos dados, o termo correspondente e a justificativa. O atendimento anterior termina no dia precedente. Uma transferência que substitui o primeiro dia marca a versão anterior como sem atendimento, preservando seu registro. A transferência precisa começar dentro do último período, sem retroagir ou reescrever uma transferência futura já registrada.

Em **Encerrar / cancelar**, a data informada é o primeiro dia sem transporte. As vagas deixam de ser ocupadas nessa data, inclusive nos períodos futuros; o atendimento anterior continua nas consultas históricas. É possível programar o término. Esta versão não reabre matrículas encerradas/canceladas e não permite uma segunda matrícula para o mesmo aluno e ano.

## Controle de vagas

As datas de atendimento são inclusivas. Cada matrícula aprovada ocupa um lugar em cada operação selecionada nos respectivos dias semanais. Horários conflitantes do mesmo aluno são rejeitados. A consulta na operação informa capacidade, ocupação e vagas previstas para a data; dias sem atendimento e ausência de veículo não são apresentados como capacidade zero.

A aprovação e a transferência conferem **todo o período**: mudanças de veículo, capacidade, início e fim das matrículas existentes. Uma vaga disponível no primeiro dia não é suficiente se houver lotação em outro dia. Lacunas de alocação que coincidem apenas com dias sem atendimento são permitidas. Não há calendário de feriados nem cálculo de deslocamento entre operações.

As decisões de matrícula, referências escolares e alocações usam a mesma trava transacional do transporte. No MySQL/InnoDB isso serializa a conferência e a reserva entre gravações da aplicação. Falhas revertem a matrícula, as datas e a auditoria da transação; arquivos criados por tentativas rejeitadas são removidos, mantendo o upload temporário disponível para nova tentativa.

## Substituição de veículo

Em **Liberar / substituir**, selecione o veículo substituto e a data. Havendo alunos no período, a liberação sem substituto é bloqueada. Liberação e nova alocação são confirmadas na mesma transação. Um substituto insuficiente, indisponível ou com horário conflitante mantém a alocação anterior intacta.

Marcar um veículo como indisponível continua preservando suas alocações e sinaliza a necessidade de regularização. Isso não cancela matrículas automaticamente. Também não apaga a ocupação histórica. Não é possível aprovar novos atendimentos dependentes de veículo atualmente indisponível.

As escolas, séries, turnos e ofertas com atendimento aprovado vigente/futuro não podem ser desativadas antes de transferir ou encerrar as matrículas. Ano e programação das operações preservam a identidade e a vigência dos registros históricos, inclusive rascunhos.

## Escopo da próxima entrega

Este módulo é a base administrativa da matrícula efetiva. A solicitação pelo responsável será uma entidade própria, distinta do rascunho administrativo e da matrícula aprovada.

Permanecem para a próxima entrega: portal e autenticação dos responsáveis; primeira solicitação e renovação; geração e download do modelo institucional do termo; envio pelo portal; fila de análise, recusa, complementação documental e lista de espera. O termo usado nesta etapa precisa ser fornecido pela secretaria. Critérios institucionais de elegibilidade, demais documentos e regras de prioridade ainda precisam ser definidos.

Sem geração de termo nesta versão, sem reabertura de matrícula terminada, sem importação de matrículas v0.4 pelo importador legado SQLite e sem alterações retroativas. O importador continua destinado ao esquema 0.3/0.3.1 e rejeita bancos de origem com tabelas adicionais.
