# Etapa 03 — Linhas, frota e operações

Entrega incremental 0.3, outubro de 2026. Mantém os cadastros, contas, permissões e histórico das etapas anteriores. Não requer recriação do banco ou da chave da aplicação.

## Escopo

| Cadastro | Finalidade |
|---|---|
| Linha | Identidade permanente do trajeto: código, nome, descrição e situação |
| Veículo | Placa, prefixo, fabricante, modelo, ano, tipo, propriedade, capacidade para alunos e situação atual |
| Operação | Atendimento de uma linha em um ano letivo e turno, com período, dias e horário |
| Alocação | Veículo designado para uma operação durante um período, capacidade registrada e justificativa |
| Revisão | Autor, data, motivo e valores anteriores e posteriores da alteração |

O menu Transporte contém Linhas, Operações das linhas e Veículos. O painel apresenta os totais de linhas ativas, veículos, veículos indisponíveis e operações dentro da vigência. O indicador de operações vigentes não afirma que há atendimento no dia da consulta.

## Regras adotadas nesta etapa

- Código de linha e placa normalizados e únicos. Aceita placas brasileiras no formato antigo e Mercosul; não consulta bases externas.
- Capacidade corresponde a assentos destinados aos alunos. A interface exige valor inteiro de 1 a 200 como limite técnico de cadastro; isso não certifica a lotação legal do veículo.
- A vigência da operação deve estar dentro do ano letivo. Linha e turno ativos e ano não encerrado são necessários para ativar ou alocar uma operação. Ano em planejamento pode receber planejamento de transporte.
- Dias semanais usam segunda a domingo; pelo menos um deles deve ocorrer dentro do período informado.
- Horário inicial deve preceder o final no mesmo dia. Para ida e volta em intervalos distintos, cadastre operações separadas.
- Bloqueia operação ativa duplicada para mesma linha, ano, turno, intervalo de datas e horário com ocorrência real de um dia comum.
- Uma operação tem no máximo um veículo alocado por período. Múltiplos veículos simultâneos na mesma operação não são suportados nesta etapa.
- Um veículo pode atender operações diferentes se não houver conflito real de datas, dias semanais e horário. Os horários usam intervalo inicial inclusivo e final exclusivo: 06h–08h e 08h–10h podem compartilhar o veículo.
- A alocação inclui as datas inicial e final. Não permite cadastrar ou liberar retroativamente; aceita a data atual. A unidade de liberação é o dia, sem distinção de viagens já realizadas no mesmo dia.
- A capacidade é copiada do veículo para a alocação. Alterar cadastro futuro não recalcula capacidade histórica.
- Veículos em manutenção, indisponíveis ou inativos não recebem novas alocações. Alterar para um desses estados mantém a programação registrada e apresenta aviso na consulta de capacidade. A substituição exige ação administrativa explícita.
- Nome e situação da operação podem ser corrigidos, mas seus campos de programação ficam imutáveis após qualquer alocação, inclusive cancelada. Crie uma nova operação para mudar a programação.
- Veículo com histórico conserva sua placa. Alteração de capacidade é bloqueada enquanto houver alocações não canceladas vigentes ou futuras.
- Linha, turno ou ano com operações ativas vigentes/futuras não podem ser desativados/encerrados. Datas e número do ano letivo não podem invalidar operações existentes, inclusive históricas.

## Liberação e substituição

1. Abra a operação e selecione a alocação a liberar.
2. Informe o primeiro dia em que o veículo não estará mais reservado.
3. Se essa data for o início da alocação, o planejamento inteiro é marcado como cancelado. Caso contrário, a vigência termina no dia anterior à liberação.
4. Autor, motivo, data do registro e término originalmente previsto são preservados. Revisões completas também ficam na auditoria.
5. Aloque o substituto a partir da data de liberação.

A liberação e a nova alocação são duas ações. Durante o intervalo entre elas, a consulta informa que não há veículo alocado. O sistema não apresenta capacidade zero como se fosse capacidade confirmada.

## Capacidade e histórico

A consulta por data distingue: fora da vigência, dia sem atendimento, sem veículo e capacidade prevista. A situação atual do veículo e dos cadastros gera aviso separado, inclusive quando se consulta uma data anterior. O aviso não afirma qual era a situação histórica da frota naquele dia.

Alunos matriculados, ocupação, vagas livres e lista de espera dependem da próxima etapa. Ainda não há calendário de feriados, exceções de viagem, cálculo de deslocamento entre operações, georreferenciamento, escolas/paradas vinculadas ao trajeto ou roteirização.

## Permissões e gravações

- `transporte.visualizar`: consulta dos três cadastros, operações e histórico cadastral.
- `transporte.editar`: cria e altera linhas e operações.
- `frota.editar`: cria e altera veículos.
- `alocacoes.gerenciar`: aloca e libera veículos.

Administrador, Gestor e Operador recebem as quatro permissões iniciais. Consulta/Auditoria recebe leitura. As permissões podem ser ajustadas nos perfis; novas execuções do seeder preservam revogações existentes. Permissões de escrita implicam consulta ao serem configuradas pelo gerenciador de perfis.

Toda edição exige motivo e a versão esperada do registro. IDs sensíveis são protegidos pelo Livewire e as permissões são verificadas novamente no servidor. Campos enviados fora da lista permitida são ignorados.

As decisões de cadastro e alocação ocorrem em transações. Uma linha de bloqueio comum serializa as gravações de transporte em PostgreSQL antes das verificações de agenda. SQLite usa sua serialização de escrita e retentativas de transação. Validação com múltiplas conexões PostgreSQL ainda é necessária antes da implantação.

## Entrega técnica

Migration incremental `2026_10_06_000004_create_transport_module.php`; serviços de cadastros, programação, alocação e consulta de capacidade; componentes Livewire; CSS local; testes automatizados isolados. Nenhuma conta ou veículo real é criado automaticamente pela etapa.

Motoristas, manutenção detalhada, inspeções/documentos, portal do responsável, termo GOV.BR e matrícula efetiva continuam pendentes. A situação operacional do veículo não equivale a uma verificação de conformidade documental.
