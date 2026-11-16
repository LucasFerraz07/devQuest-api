# Guia de implementação do front-end DevQuest

Este documento orienta o agente responsável pelo front-end a implementar as funcionalidades na ordem das dependências reais da API. O inventário contém as **39 operações de produto** existentes: 5 de autenticação, 11 da jornada/perfil e 23 de administração.

Use as tipagens disponíveis e o [OpenAPI](docs/openapi.json) para os contratos. Cada chamada é identificada primeiro pelo `operationId` em camelCase, seguido do endpoint HTTP completo e do grupo atual no Scramble, sem reproduzir corpos de requisição ou resposta. Os endpoints das tabelas já incluem **`/api`**; preserve os nomes dos parâmetros do contrato. Se o cliente já possui uma URL base terminada em `/api`, utilize somente o trecho restante do caminho para evitar `/api/api`. Os caminhos das páginas do front-end ficam a critério do projeto.

O levantamento foi conferido com as rotas registradas pelo Laravel, os controllers, services, policies e testes. Consulte também o [README](README.md).

## Como referenciar e chamar as operações

Use o `operationId` exato indicado nas tabelas para localizar a operação nas tipagens ou no cliente gerado. Por exemplo, `showStage` consulta uma etapa e `startStage` inicia seu progresso; `showLesson` consulta uma aula e `completeLesson` marca sua conclusão. A organização do backend separa esses recursos em controllers e pastas próprios.

Os métodos internos dos controllers (`index`, `show`, `store`, `update` e `destroy`) não substituem os `operationId`s na integração. O início de etapa é implementado por `StageProgressController::store`, mas o front identifica essa chamada como `startStage`. A conclusão da aula usa `LessonCompletionController::update`, identificada como `completeLesson`.

O Scramble organiza as operações em 17 grupos por recurso/controller. A coluna de grupo nas tabelas serve para localizar cada operação na documentação; a forma de exportar ou agrupar funções no cliente depende do gerador usado pelo front.

As operações administrativas agora usam nomes como `adminLearningPathsIndex`, `adminLearningPathsUpdate` e `adminLearningPathsStagesReorder`. Regenere o cliente/tipagens a partir do OpenAPI atual e substitua referências antigas com pontos ou hífens. Os nomes internos das rotas Laravel, como `admin.learning-paths.index`, não são os identificadores que o front deve utilizar. As URLs e os métodos HTTP foram preservados.

## Ordem de entrega

| Fase | Entrega | Dependência para avançar |
| --- | --- | --- |
| 0 | Cliente HTTP, erros e estrutura de navegação | Comunicação e estados básicos prontos |
| 1 | Cadastro, login, sessão, renovação e logout | Usuário autenticado e papel conhecido |
| 2 | Catálogo, mapa da trilha e visão da etapa | Navegação e bloqueios funcionando |
| 3 | Leitura e conclusão de aulas | Progresso de leitura persistido |
| 4 | Exercícios, feedback e conclusão da jornada | Fluxo de estudo completo e dados sincronizados |
| 5 | Painel, retomada, histórico e conquistas | Experiência do aluno completa |
| 6 | CRUD administrativo em rascunho | Hierarquia completa de conteúdo editável |
| 7 | Reordenação, publicação e restrições editoriais | Ciclo de gestão de conteúdo completo |

Entregue primeiro a jornada do aluno, da fase 1 à 4. Para desenvolvê-la antes do editor administrativo, use a trilha opcional criada por `php artisan db:seed --class=DemoContentSeeder`, conforme o README. O painel definitivo vem depois da jornada porque seus atalhos dependem das telas de aula e exercício. A implementação do editor segue a hierarquia trilha → etapa → aulas/exercícios; a publicação segue a ordem inversa.

## Fase 0 — Base de integração

Implemente antes das telas de produto:

- URL base configurável por ambiente, headers JSON e autenticação Bearer JWT. Configure a origem do front em `FRONTEND_URLS` na API.
- Consumo das operações pelas tipagens existentes, preservando o `operationId` como referência. Não crie uma segunda definição manual dos contratos.
- Camada de sessão e cache com isolamento por usuário. Limpe os dados privados ao sair ou trocar de conta.
- Componentes e estados de carregamento, lista vazia, erro, nova tentativa e ação em andamento. Nas escritas, desabilite o envio duplicado enquanto houver uma chamada pendente.
- Paginação usando os metadados da API. O catálogo usa 12 itens por padrão; administração, histórico e conquistas usam 20. O limite por página é 100.
- Estados de erro abaixo, reutilizados desde os primeiros formulários.

| Situação | Comportamento do front-end |
| --- | --- |
| 401 em operação autenticada | Executar a renovação da fase 1; voltar ao login se a sessão não puder ser recuperada |
| 401 no login | Exibir falha de credenciais no formulário |
| 403 | Exibir acesso indisponível, inclusive em links diretos para etapas bloqueadas ou telas administrativas |
| 404 | Exibir recurso não encontrado e oferecer retorno à navegação |
| 409 | Exibir a mensagem de conflito e atualizar o conteúdo afetado; no editor, preservar o texto ainda não salvo |
| 422 | Associar os erros aos respectivos campos, inclusive alternativas de exercício |
| 429 | Respeitar `Retry-After` e informar o tempo de espera |
| Falha de rede ou 500 | Exibir erro recuperável; não repetir submissões automaticamente, pois cada tentativa é um registro |

**Pronto quando:** todas as próximas telas puderem usar o mesmo cliente, tratamento de erro e componentes de estado.

## Fase 1 — Autenticação e sessão

| operationId | Endpoint HTTP | Grupo no Scramble | Feature / momento de uso |
| --- | --- | --- | --- |
| `registerUser` | `POST /api/auth/register` | Autenticação | Cadastro público de aluno e entrada na aplicação após sucesso |
| `loginUser` | `POST /api/auth/login` | Autenticação | Login de aluno e administrador |
| `getAuthenticatedUser` | `GET /api/auth/me` | Autenticação | Recuperação do usuário ao restaurar a sessão e identificação do papel |
| `refreshToken` | `POST /api/auth/refresh` | Autenticação | Renovação centralizada do token |
| `logoutUser` | `POST /api/auth/logout` | Autenticação | Encerramento da sessão no servidor e limpeza local |

Implemente cadastro com confirmação de senha, login, saída e restauração da sessão ao recarregar a página. O cadastro público sempre cria `student`; não ofereça seleção de papel. O acesso administrativo usa uma conta `admin` já provisionada no backend.

Proteja as páginas autenticadas e, desde esta fase, prepare a proteção administrativa por papel. A navegação deve aguardar a restauração da sessão antes de redirecionar ou exibir conteúdo privado. O catálogo também exige autenticação.

A renovação utiliza o JWT atual no header, inclusive quando o acesso já expirou dentro da janela de renovação. Ela invalida o token anterior: serialize chamadas concorrentes em uma única renovação compartilhada e repita a operação original no máximo uma vez após sucesso. Não aplique essa lógica recursivamente à própria renovação nem ao login/cadastro. Se a renovação retornar 401, encerre a sessão local. A janela de renovação conta desde a emissão original e não reinicia a cada renovação.

**Pronto quando:** cadastro e login abrem a área autenticada, recarregar restaura a sessão, chamadas concorrentes não disparam várias renovações e logout impede reutilizar os dados privados da conta anterior.

## Fase 2 — Catálogo, mapa da trilha e etapa

| operationId | Endpoint HTTP | Grupo no Scramble | Feature / momento de uso |
| --- | --- | --- | --- |
| `indexLearningPaths` | `GET /api/learning-paths` | Trilhas de aprendizado | Catálogo paginado, busca por título e cartões com capa, contagens e progresso |
| `showLearningPath` | `GET /api/learning-paths/{learningPath}` | Trilhas de aprendizado | Detalhe da trilha e mapa ordenado de etapas |
| `showStage` | `GET /api/stages/{stage}` | Etapas | Visão da etapa com resumos ordenados das aulas e exercícios e progresso individual |
| `startStage` | `POST /api/stages/{stage}/start` | Progresso de etapas | Ação explícita de iniciar uma etapa disponível |

Implemente nesta ordem: catálogo → mapa → detalhe da etapa → ação de iniciar. A busca deve reiniciar a paginação e permitir distinguir catálogo vazio de busca sem resultados. Apresente a capa com fallback quando ausente.

No mapa, represente `locked`, `available`, `in_progress` e `completed`. A primeira etapa publicada fica disponível; as próximas dependem da conclusão da anterior. Use a ordem e os estados fornecidos pelo backend, inclusive ao reabrir uma trilha. Mostre os requisitos da etapa: aulas concluídas, exercícios tentados, exercícios resolvidos e nota mínima.

Consultar catálogo, mapa ou etapa não cria progresso. Chame `startStage` pelo botão de iniciar, sem dispará-la ao montar a tela. Iniciar é idempotente; concluir uma aula ou responder um exercício também inicia a etapa automaticamente. Depois do início, atualize a etapa e invalide mapa, catálogo e painel, caso estejam em cache.

Carregue o conteúdo completo de cada aula ou exercício apenas ao abri-lo, nas fases seguintes. Não carregue todos os detalhes antecipadamente. O bloqueio visual deve acompanhar os estados da API; links diretos continuam sujeitos a 403. As rotas da jornada só mostram conteúdo publicado, inclusive para um administrador; a inspeção de rascunhos será feita pelo editor.

**Pronto quando:** o aluno encontra uma trilha, vê a sequência correta, inicia uma etapa disponível e identifica seus itens e requisitos sem criar progresso apenas por navegar.

## Fase 3 — Aulas e conclusão de leitura

| operationId | Endpoint HTTP | Grupo no Scramble | Feature / momento de uso |
| --- | --- | --- | --- |
| `showLesson` | `GET /api/lessons/{lesson}` | Aulas | Leitor de aula, situação de conclusão e navegação para aula anterior/próxima |
| `completeLesson` | `PUT /api/lessons/{lesson}/completion` | Conclusão de aulas | Botão explícito de marcar a aula como concluída |

Crie um leitor de Markdown com sanitização, apresentação legível de blocos de código e retorno à etapa. Use os identificadores de navegação fornecidos pela API. O término das aulas deve permitir voltar à etapa e acessar os exercícios.

A conclusão depende da confirmação do aluno: não a dispare por abrir a tela, rolar a página ou permanecer um tempo no leitor. A operação é idempotente e não existe endpoint para desfazer a conclusão.

Após chamar `completeLesson`, reflita o resultado confirmado pelo servidor na aula e na etapa. Prepare já o tratamento compartilhado de progresso da fase 4: uma aula pode ser o último requisito pendente e sua conclusão também pode concluir a etapa, liberar a próxima e conceder conquistas.

**Pronto quando:** a leitura pode ser marcada uma vez ou repetida sem duplicar progresso, e o estado concluído permanece após recarregar a página.

## Fase 4 — Exercícios e fechamento da jornada

| operationId | Endpoint HTTP | Grupo no Scramble | Feature / momento de uso |
| --- | --- | --- | --- |
| `showExercise` | `GET /api/exercises/{exercise}` | Exercícios | Tela de exercício com enunciado, alternativas e situação pessoal |
| `submitExercise` | `POST /api/exercises/{exercise}/submissions` | Submissões | Envio de uma alternativa, feedback de acerto/erro e novas tentativas |

Implemente exercícios de múltipla escolha: seleção de uma alternativa, envio, espera, resultado e nova tentativa por ação explícita. O aluno pode acessar os exercícios de uma etapa disponível antes de concluir as aulas; a leitura é requisito para concluir a etapa, não para responder.

Não busque gabaritos administrativos para a área do aluno. A alternativa correta não é exposta nessas consultas. Exiba a explicação somente quando disponibilizada pela API, após o primeiro acerto. Diferencie exercício tentado de exercício resolvido; um erro posterior não apaga um acerto anterior.

Finalize nesta fase o tratamento de progresso compartilhado por submissão e conclusão de aula:

- Apresente XP concedido, XP total e nível conforme o resultado do servidor. Somente o primeiro acerto de cada exercício concede XP; uma nova tentativa correta pode conceder zero.
- Atualize a etapa e o usuário com os dados confirmados. Atualize também a aula quando a ação for conclusão de leitura.
- Mostre novas conquistas recebidas na ação, sem repetir a celebração de recompensas antigas.
- Ao confirmar conclusão da etapa, ofereça acesso à próxima liberada. Um identificador de próxima etapa no detalhe da etapa indica sequência e, sozinho, não comprova desbloqueio; confirme o resultado da ação e o estado do mapa.
- Diferencie término de etapa de término da trilha. A ausência de próxima etapa liberada não basta para declarar a trilha concluída: a etapa atual pode ainda ter requisitos pendentes.
- Invalide as consultas afetadas: mapa da trilha, catálogo, painel, aula/exercício envolvido, histórico e conquistas. Aplique isso também quando essas telas forem implementadas na fase 5.

O backend conclui a etapa automaticamente quando todas as aulas publicadas foram concluídas, todos os exercícios publicados receberam ao menos uma tentativa e a nota atingiu o mínimo. A nota considera os pontos dos exercícios já acertados em relação ao total de pontos, e não apenas a última tentativa. Não existe operação separada de concluir etapa, concluir trilha ou conceder XP/conquista; use o estado calculado pelo servidor.

**Pronto quando:** o aluno consegue concluir uma trilha inteira, repetir tentativas sem duplicar XP e ver bloqueios e progresso corretos após navegar ou recarregar. Valide também o caso de exercícios respondidos antes da última leitura: a conclusão da aula deve finalizar a etapa quando for o requisito restante.

## Fase 5 — Painel, retomada, histórico e conquistas

| operationId | Endpoint HTTP | Grupo no Scramble | Feature / momento de uso |
| --- | --- | --- | --- |
| `showDashboard` | `GET /api/me/dashboard` | Painel | Início do aluno com XP, nível, contagens, trilhas iniciadas e próxima atividade |
| `indexSubmissions` | `GET /api/me/submissions` | Submissões | Histórico paginado de tentativas, com filtros por exercício e resultado |
| `indexAchievements` | `GET /api/me/achievements` | Conquistas | Galeria paginada de conquistas obtidas e respectivas datas |

Transforme o painel na entrada principal do aluno. O botão de continuar deve abrir a aula ou exercício indicado pela API, com fallback para a etapa quando não houver item específico. Sem próxima atividade, ofereça acesso ao catálogo e às trilhas já iniciadas; não invente uma recomendação obrigatória.

No histórico, exiba resultado, XP concedido e data de cada tentativa. Permita abrir o histórico a partir do exercício já filtrado. A lista pertence sempre ao usuário autenticado; não crie seletores de aluno.

Na galeria, exiba apenas as conquistas obtidas. Não há endpoint para catálogo de conquistas bloqueadas nem para resgatá-las manualmente. Formate as datas UTC para o fuso do usuário.

Inclua estados de primeiro acesso, ausência de tentativas, ausência de conquistas e trilhas concluídas. Uma trilha despublicada deixa de estar disponível na jornada; ao receber 403, volte para uma navegação válida e atualize as consultas. O histórico é preservado no backend.

**Pronto quando:** o aluno retoma os estudos por um atalho válido e painel, histórico, galeria e cabeçalho apresentam os mesmos dados após as ações de estudo.

## Fase 6 — Administração: CRUD em rascunho

Implemente após a experiência do aluno. Restrinja menu e páginas ao papel `admin`, mantendo o tratamento de 403 em cada operação. Use exclusivamente os endpoints administrativos para ler e editar rascunhos, alternativas corretas e demais dados editoriais.

Em cada grupo abaixo, implemente lista → criação → consulta/edição → exclusão com confirmação. Comece pelos pais para que os formulários dos filhos já tenham contexto. Use navegação trilha → etapas → aulas/exercícios, filtros de publicação e busca por título, além de paginação. As etapas podem ser filtradas pela trilha; aulas e exercícios, pela etapa. Uma lista administrativa paginada não contém necessariamente todos os filhos do agrupamento.

### 6.1 Trilhas

| operationId | Endpoint HTTP | Grupo no Scramble | Feature |
| --- | --- | --- | --- |
| `adminLearningPathsIndex` | `GET /api/admin/learning-paths` | Administração: trilhas de aprendizado | Listagem e busca administrativa de trilhas |
| `adminLearningPathsStore` | `POST /api/admin/learning-paths` | Administração: trilhas de aprendizado | Criação da trilha em rascunho |
| `adminLearningPathsShow` | `GET /api/admin/learning-paths/{record}` | Administração: trilhas de aprendizado | Carregamento do detalhe para edição |
| `adminLearningPathsUpdate` | `PATCH /api/admin/learning-paths/{record}` | Administração: trilhas de aprendizado | Edição da trilha; publicação será habilitada na fase 7 |
| `adminLearningPathsDestroy` | `DELETE /api/admin/learning-paths/{record}` | Administração: trilhas de aprendizado | Exclusão quando permitida pelo backend |

Ofereça edição de título, slug, descrição e URL de capa, com prévia da imagem. Não há endpoint de produto para upload de capa. Depois de criar a trilha, permita gerenciar suas etapas.

### 6.2 Etapas

| operationId | Endpoint HTTP | Grupo no Scramble | Feature |
| --- | --- | --- | --- |
| `adminStagesIndex` | `GET /api/admin/stages` | Administração: etapas | Listagem de etapas, com filtro pela trilha |
| `adminStagesStore` | `POST /api/admin/stages` | Administração: etapas | Criação da etapa em rascunho dentro de uma trilha |
| `adminStagesShow` | `GET /api/admin/stages/{record}` | Administração: etapas | Carregamento da etapa para edição |
| `adminStagesUpdate` | `PATCH /api/admin/stages/{record}` | Administração: etapas | Edição editorial, posição e nota mínima, sujeita às restrições da fase 7 |
| `adminStagesDestroy` | `DELETE /api/admin/stages/{record}` | Administração: etapas | Exclusão quando permitida pelo backend |

Apresente a trilha à qual a etapa pertence, a posição e o critério de aprovação. O vínculo com a trilha não pode ser alterado após a criação. A partir da etapa, permita administrar aulas e exercícios em listas próprias.

### 6.3 Aulas

| operationId | Endpoint HTTP | Grupo no Scramble | Feature |
| --- | --- | --- | --- |
| `adminLessonsIndex` | `GET /api/admin/lessons` | Administração: aulas | Listagem de aulas, com filtro pela etapa |
| `adminLessonsStore` | `POST /api/admin/lessons` | Administração: aulas | Criação de aula em rascunho |
| `adminLessonsShow` | `GET /api/admin/lessons/{record}` | Administração: aulas | Carregamento do conteúdo completo para edição |
| `adminLessonsUpdate` | `PATCH /api/admin/lessons/{record}` | Administração: aulas | Edição de aula e, na fase 7, publicação |
| `adminLessonsDestroy` | `DELETE /api/admin/lessons/{record}` | Administração: aulas | Exclusão quando permitida pelo backend |

Implemente editor de Markdown com prévia sanitizada usando a mesma apresentação do leitor, posição e tempo estimado. A etapa vinculada é imutável após a criação.

### 6.4 Exercícios

| operationId | Endpoint HTTP | Grupo no Scramble | Feature |
| --- | --- | --- | --- |
| `adminExercisesIndex` | `GET /api/admin/exercises` | Administração: exercícios | Listagem de exercícios, com filtro pela etapa |
| `adminExercisesStore` | `POST /api/admin/exercises` | Administração: exercícios | Criação de exercício em rascunho |
| `adminExercisesShow` | `GET /api/admin/exercises/{record}` | Administração: exercícios | Edição do exercício com alternativas e gabarito |
| `adminExercisesUpdate` | `PATCH /api/admin/exercises/{record}` | Administração: exercícios | Edição do exercício e, na fase 7, publicação |
| `adminExercisesDestroy` | `DELETE /api/admin/exercises/{record}` | Administração: exercícios | Exclusão quando permitida pelo backend |

Implemente primeiro múltipla escolha: enunciado, dificuldade, pontos, explicação, posição e editor de alternativas com exatamente uma correta. São necessárias de 2 a 20 alternativas, inclusive para salvar em rascunho. O vínculo com a etapa é imutável.

Salvar alternativas substitui o conjunto inteiro e gera novos identificadores. O editor deve enviar o conjunto completo quando houver alteração e adotar os identificadores atualizados após salvar, usando as tipagens de entrada. Em uma correção apenas editorial, não reenvie alternativas sem necessidade: isso conta como mudança estrutural.

Ao terminar o CRUD de múltipla escolha, suporte armazenamento/edição dos campos de exercícios de código somente como rascunho administrativo. Deixe essa limitação clara no editor. Não implemente execução, submissão nem publicação desse tipo; também não existe CRUD de testes de código entre as rotas atuais.

**Pronto quando:** um administrador consegue criar e reabrir uma hierarquia completa em rascunho, editar seus textos e alternativas e excluir conteúdo permitido. O aluno não consegue acessar essas operações nem por URL direta.

## Fase 7 — Reordenação, publicação e manutenção

### Reordenação

| operationId | Endpoint HTTP | Grupo no Scramble | Feature |
| --- | --- | --- | --- |
| `adminLearningPathsStagesReorder` | `PUT /api/admin/learning-paths/{record}/stages/order` | Administração: ordem de etapas | Reordenar as etapas de uma trilha |
| `adminStagesLessonsReorder` | `PUT /api/admin/stages/{record}/lessons/order` | Administração: ordem de aulas | Reordenar as aulas de uma etapa |
| `adminStagesExercisesReorder` | `PUT /api/admin/stages/{record}/exercises/order` | Administração: ordem de exercícios | Reordenar os exercícios de uma etapa |

Implemente controles de mover ou arrastar e salvar ordem. Antes de enviar, carregue todos os itens do agrupamento, percorrendo a paginação, incluindo rascunhos e sem filtros de busca/publicação que ocultem itens. Cada operação exige o conjunto completo e exclusivo daquele pai. Não há reordenação de trilhas no catálogo nem operação equivalente para misturar aulas e exercícios numa única sequência.

Confirme a ordem somente após sucesso; em erro, restaure a ordem anterior ou recarregue a lista. Reordenação e mudança manual de posição devem respeitar as restrições abaixo.

### Publicação

Use `adminLearningPathsUpdate`, `adminStagesUpdate`, `adminLessonsUpdate` e `adminExercisesUpdate`, implementadas na fase 6, para publicar/despublicar seus respectivos recursos via `PATCH`; não existe endpoint separado de publicação. Habilite o fluxo nesta ordem:

1. Criar a trilha e as etapas em rascunho.
2. Criar, revisar e publicar as aulas e os exercícios de múltipla escolha.
3. Publicar cada etapa depois de possuir ao menos uma aula publicada e um exercício válido publicado.
4. Publicar a trilha depois de possuir ao menos uma etapa publicada válida.
5. Conferir a experiência do aluno pelo catálogo e mapa, respeitando as mesmas regras de acesso da jornada.

Mostre os estados rascunho/publicado e explique os erros de integridade junto à ação. Despublicar ou excluir um filho não pode invalidar uma etapa/trilha ainda publicada; permita ajustar a publicação dos pais antes, quando a API autorizar. Exercícios de código permanecem em rascunho.

### Conteúdo de trilhas iniciadas

Assim que qualquer aluno inicia uma trilha, a API bloqueia inclusão/exclusão de conteúdo, reordenação, troca de alternativas/gabarito, pontos, dificuldade, tipo, critérios e publicação individual de etapas, aulas e exercícios. A restrição vale para a trilha inteira.

Correções editoriais continuam permitidas: títulos, descrições, capa, slug, conteúdo de aula, tempo estimado, enunciado e explicação, conforme o tipo. A publicação/despublicação da própria trilha continua disponível e preserva o histórico.

O contrato administrativo atual não fornece um indicador específico de bloqueio estrutural. Não infira que uma edição será permitida apenas porque a tela a oferece. Trate 409 como estado de negócio, preserve o trabalho editorial e explique a restrição retornada. Não envie um formulário completo com campos estruturais ou alternativas inalteradas ao salvar somente textos.

Depois de salvar, publicar, excluir ou reordenar, atualize as listas/detalhes administrativos afetados e invalide as consultas da jornada que estiverem em cache na mesma sessão. Se um conteúdo deixar de existir ou de estar acessível, redirecione para o pai ou para a listagem válida.

**Pronto quando:** o administrador publica uma trilha íntegra, altera a ordem antes do primeiro início e, após existir progresso, consegue corrigir textos e despublicar a trilha enquanto recebe tratamento claro para mudanças estruturais proibidas.

## Verificação final da integração

Execute estes cenários conforme as fases forem concluídas; ao final, valide o fluxo completo com a API real:

1. Cadastro, login, restauração da sessão, renovação concorrente, expiração definitiva e logout; troca de conta sem dados da conta anterior.
2. Catálogo com busca/paginação, mapa com etapas bloqueadas e acesso direto negado. Navegar sem iniciar não deve criar progresso.
3. Conclusão de aula repetida, resposta errada seguida de acerto e repetição de acerto sem XP extra. Histórico deve preservar cada tentativa.
4. Etapa com nota suficiente mas leitura pendente; etapa com leitura concluída mas exercício não tentado; etapa abaixo da nota mínima. Nenhuma deve aparecer como concluída antecipadamente.
5. Conclusão pela última resposta e pela última aula, desbloqueio da próxima etapa, término da trilha, conquistas únicas e atualização de painel/mapa/catálogo.
6. Criação administrativa em rascunho, validação de alternativas, publicação na ordem correta, reordenação com todos os itens e exclusão permitida.
7. Aluno impedido de acessar administração; após início da trilha, mudanças estruturais impedidas e correções editoriais/despublicação da trilha permitidas.

## Rotas técnicas fora da implementação de produto

O Laravel também registra as rotas abaixo, sem `operationId` no contrato da API. Elas não exigem telas nem integrações de produto no front-end. `HEAD` acompanha automaticamente as rotas `GET`.

| Rota na raiz do servidor | Finalidade |
| --- | --- |
| `GET /` | Página de boas-vindas do backend |
| `GET /up` | Health check |
| `GET /docs/api` | Interface de documentação, com acesso restrito pelo Scramble |
| `GET /docs/api.json` | Documento OpenAPI servido pelo Scramble, com acesso restrito |
| `GET /_scramble/dev-tools/devtools.js` | Asset interno da ferramenta de documentação |
| `GET /storage/{path}` | Rota técnica do filesystem local |
| `PUT /storage/{path}` | Rota técnica de upload do filesystem, sujeita às regras do Laravel; não representa uma feature de upload de conteúdo DevQuest |

## Funcionalidades sem suporte nesta versão

Não planeje telas dependentes de endpoints inexistentes: recuperação/redefinição de senha, edição de perfil ou troca de senha, gestão de usuários/papéis, ranking, execução/submissão de código, upload de capa dedicado, versionamento/duplicação de conteúdo, reset de progresso e resgate manual de conquistas. Qualquer ampliação desse escopo depende de uma nova entrega do backend.
