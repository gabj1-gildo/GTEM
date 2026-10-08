# Etapa 02 — Alunos, responsáveis e vínculos

Base funcional: planejamento_transporte_escolar_v2.md, seções 4, 10, 34–36, 39, 47 e 53.

## Entrega e rastreabilidade

| Requisito | Implementação |
|---|---|
| RF-010 | Cadastro, pesquisa, edição e inativação de responsáveis |
| RF-011 | Cadastro, pesquisa, edição e inativação de alunos |
| RF-012 | Vínculos com tipo, principal, vigência e histórico |
| RF-013 / RN-39 | Autorização explícita no vínculo; verificação de vínculo vigente e pessoas ativas |
| RF-014 / RF-015 | Ficha com versões cadastrais e vínculos anteriores |
| RF-005 / RN-21 | Auditoria com ator, campos alterados, data e justificativa |
| RF-006 | Inativação, sem exclusão física pela aplicação |
| RNF-003 / RNF-006 | Permissões no servidor e verificação em cada ação |
| RNF-008 | Versões de vínculos e revisões de dados preservadas |
| RNF-014 | Validação no servidor de datas, CPF, contato e autorizações |
| RNF-017 | Auditoria geral sem copiar CPF ou os demais dados cadastrais completos |
| RNF-019 | Testes de integração e regras críticas |

RN-39 possui agora uma função de domínio para a elegibilidade do vínculo; o módulo de solicitação deverá chamá-la novamente no envio, sem confiar em uma avaliação antiga ou apenas no controle visual.

## Modelo

- `students`: identidade permanente do aluno, nascimento, situação e CPF opcional.
- `guardians`: identidade do responsável, situação e contato.
- `guardian_student_links`: relação com vigência, autorização, principal, atores e justificativas.
- `person_revisions`: mudanças cadastrais criptografadas.

Escola, série, turno, endereço da solicitação e linha não foram adicionados ao cadastro permanente do aluno. Esses dados pertencerão ao contexto anual da solicitação/matrícula.

## Regras implementadas

1. CPF informado deve conter 11 dígitos válidos. Pontuação comum é removida.
2. CPFs iguais dentro do cadastro de alunos ou de responsáveis não podem ser duplicados. O mesmo CPF em entidades distintas não é bloqueado.
3. Mais de um cadastro sem CPF é permitido.
4. Aluno exige data de nascimento. Não se aceita data futura; nascimento do responsável é opcional.
5. Uma edição pessoal exige justificativa e a versão que foi originalmente aberta.
6. Uma gravação desatualizada é rejeitada; o operador precisa reabrir os dados atuais.
7. Um par aluno/responsável pode ter vários vínculos históricos, mas apenas um aberto.
8. Um aluno pode ter vários responsáveis, mas apenas um principal aberto.
9. Ser responsável principal não concede automaticamente autorização de solicitação.
10. Criar ou alterar vínculo exige aluno e responsável ativos.
11. Encerrar vínculo é permitido mesmo quando alguma pessoa está inativa.
12. Autorização exige `can_request`, vigência e ambos os cadastros ativos.
13. Alterar vínculo fecha a versão antiga e cria outra na mesma transação. Um formulário de versão já encerrada não pode alterá-la.
14. O intervalo de vigência é fechado no início e aberto no fim: `início <= instante < fim`. Sem fim, a vigência continua aberta.
15. Vínculos não são apagados, e não existe edição retroativa de vigência nesta interface.
16. As operações usam transações e bloqueio da linha do aluno para serializar mudanças de vínculo em bancos que suportam `FOR UPDATE`.
17. Índices únicos parciais também protegem a relação aberta e o responsável principal no banco.
18. A inativação da pessoa não fecha seus vínculos. A checagem consulta a situação atual de ambos. A reativação pode reabilitar uma autorização vigente.

## Permissões iniciais

| Permissão | Administrador | Gestor | Operador | Consulta |
|---|---:|---:|---:|---:|
| pessoas.visualizar | Sim | Sim | Sim | Sim |
| pessoas.editar | Sim | Sim | Sim | Não |
| vinculos.gerenciar | Sim | Sim | Sim | Não |

Os perfis não administrativos permanecem configuráveis. Edição de pessoas ou gestão de vínculos inclui o acesso de consulta necessário à interface. Essas concessões iniciais são escolhas para desenvolvimento e devem ser revisadas pela Secretaria antes de uso institucional.

## Histórico e proteção dos dados

O CPF é armazenado com a criptografia do Laravel. A pesquisa exata e a unicidade usam HMAC com a chave da aplicação. As revisões completas também são criptografadas. As telas de listagem, ficha e histórico mascaram o CPF; a edição autorizada permite consultá-lo.

O histórico geral de auditoria registra identificadores e os nomes dos campos alterados. Valores pessoais anteriores e novos ficam apenas nas revisões protegidas pelo acesso ao módulo.

A aplicação preserva o histórico por seus fluxos administrativos. Esta etapa não implementa proteção contra alterações diretas feitas por administradores do banco nem trilha externa inviolável.

Toda atualização futura de pessoas deve passar por PeopleService, e toda alteração de vínculo deve passar por GuardianLinkService. Atualizações diretas em Eloquent ou SQL podem contornar as regras de aplicação.

APP_KEY deve ser preservada e protegida junto à estratégia de backup. Rotação da chave exige um procedimento específico para recriptografar os valores e recalcular os identificadores de pesquisa; esse procedimento não está implementado.

## Decisões ainda abertas

- Documentos necessários para comprovar parentesco ou representação.
- Exigência de CPF por situação.
- Critérios formais de responsável autorizado.
- Autenticação, recuperação de conta e verificação de identidade no portal.
- Regras para mudança de responsável durante uma solicitação.
- Política de retenção e tratamento de dados.
- Validação em PostgreSQL e revisão da implantação.

Nenhuma dessas definições pendentes é apresentada pela interface como verificação documental automática.
