# Integração com React

## Cliente e sessão

Configure a URL base como `http://localhost:8000/api` no ambiente do front-end e `FRONTEND_URLS` no ambiente da API. A API usa Bearer JWT; não depende de cookies, sessão Laravel ou endpoint CSRF.

```ts
type ApiResponse<T> = {
  error: boolean;
  message: string;
  data: T;
  errors?: Record<string, string[]>;
  meta?: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
};

async function api<T>(path: string, token: string, init: RequestInit = {}) {
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');
  if (init.body) headers.set('Content-Type', 'application/json');
  if (token) headers.set('Authorization', `Bearer ${token}`);
  const response = await fetch(`${import.meta.env.VITE_API_URL}${path}`, {
    ...init,
    headers,
  });
  const body = await response.json() as ApiResponse<T>;
  if (!response.ok) throw Object.assign(new Error(body.message), {
    status: response.status,
    fields: body.errors,
  });
  return body;
}
```

Guarde o token na camada de sessão do aplicativo. `POST /auth/login` e `/auth/register` retornam `data.user`, `data.token`, `data.token_type`, `data.expires_in` e `data.refresh_expires_in`. O cadastro exige `password_confirmation`; não aceita papel, XP ou nível.

Renove com `POST /auth/refresh`, enviando o token atual no header Authorization. A renovação aceita token de acesso expirado dentro da janela configurada, invalida o token anterior e retorna o novo token. Serialize renovações em uma única Promise compartilhada para evitar que chamadas concorrentes tentem usar um token já invalidado. Após renovar, repita a chamada original uma única vez. Se a renovação também retornar 401, encerre a sessão local.

`expires_in` é a validade do novo acesso, em segundos. `refresh_expires_in` informa a janela configurada de renovação, contada da emissão original; não é o tempo restante nem uma nova janela a cada renovação. `POST /auth/logout` invalida o token; limpe também a sessão local.

## Fluxo demonstrativo

Execute `php artisan db:seed --class=DemoContentSeeder`. Em seguida:

1. Cadastre-se com `POST /auth/register` ou faça login.
2. Consulte `GET /learning-paths`: cada item tem título, capa, contagens e percentual de etapas concluídas.
3. Abra `GET /learning-paths/{id}`: `data.stages` é o mapa ordenado. Os estados são `locked`, `available`, `in_progress` e `completed`.
4. Use `POST /stages/{id}/start` e abra `GET /stages/{id}`. A resposta inclui resumos ordenados de aulas e exercícios, sem carregar o conteúdo completo de todos os itens.
5. Leia `GET /lessons/{id}` e, após confirmação do aluno, envie `PUT /lessons/{id}/completion`. Renderize `content` como Markdown sanitizado. A resposta inclui a aula, a etapa atualizada, usuário, novas conquistas e a próxima etapa liberada, se houver.
6. Consulte `GET /exercises/{id}`. Apresente `options` usando seus IDs; o payload não informa a correta.
7. Responda com `POST /exercises/{id}/submissions` e `{ "selected_option_id": "UUID" }`.
8. Atualize a interface com o resultado. Ao concluir a etapa, `data.next_stage_id` indica qual foi liberada; ao concluir a trilha, é `null`.

Exemplo de resposta correta depois de concluir a leitura:

```json
{
  "error": false,
  "message": "",
  "data": {
    "submission": {
      "id": "UUID",
      "exercise_id": "UUID",
      "selected_option_id": "UUID",
      "status": "correct",
      "score_awarded": 50,
      "submitted_at": "2026-11-04T12:00:00.000000Z"
    },
    "explanation": "Use const para uma referência que não será reatribuída.",
    "user": {
      "id": "UUID",
      "name": "Aluno",
      "email": "aluno@example.com",
      "role": "student",
      "total_xp": 50,
      "current_level": 1,
      "next_level_xp": 100
    },
    "stage": {
      "id": "UUID",
      "learning_path_id": "UUID",
      "title": "Variáveis e tipos",
      "description": null,
      "position": 1,
      "minimum_score": 100,
      "status": "completed",
      "best_score": 100,
      "started_at": "2026-11-04T11:55:00.000000Z",
      "completed_at": "2026-11-04T12:00:00.000000Z",
      "lessons_count": 1,
      "completed_lessons_count": 1,
      "exercises_count": 1,
      "attempted_exercises_count": 1,
      "solved_exercises_count": 1,
      "next_stage_id": "UUID-DA-PROXIMA",
      "lessons": [],
      "exercises": []
    },
    "new_achievements": [],
    "next_stage_id": "UUID-DA-PROXIMA"
  }
}
```

Este exemplo abrevia as coleções de aulas, exercícios e conquistas. O [OpenAPI](openapi.json) define os tipos completos. `explanation` é omitida até o primeiro acerto. Uma repetição correta retorna `score_awarded: 0`; isso não muda a nota conquistada.

## Telas e atualização de cache

| Tela | Consulta | Dados a usar |
| --- | --- | --- |
| Início | `/me/dashboard` | Usuário, trilhas iniciadas, contagens e `next_activity` |
| Catálogo | `/learning-paths?page=1&per_page=12&search=javascript` | Lista leve e `meta` |
| Mapa | `/learning-paths/{id}` | Etapas, bloqueios e progresso |
| Etapa | `/stages/{id}` | Aulas e exercícios com conclusão individual |
| Aula | `/lessons/{id}` | Conteúdo, conclusão e navegação entre aulas |
| Exercício | `/exercises/{id}` | Enunciado, opções e situação pessoal |
| Histórico | `/me/submissions?exercise_id=UUID&status=correct` | Apenas tentativas do usuário atual |
| Conquistas | `/me/achievements` | Conquistas obtidas e horário |
| Editor | `/admin/*` | Conteúdo completo, publicação e gabaritos |

Depois de uma submissão ou conclusão de aula, substitua os dados da etapa e do usuário pelos valores retornados. Invalide o mapa da trilha, catálogo, painel, exercício afetado, histórico e conquistas conforme necessário. A API calcula nota, nível, bloqueios e recompensas; o React apresenta esses valores.

Consultas nunca iniciam a trilha. Um botão explícito pode chamar `/start`; responder um exercício ou concluir uma aula também inicia a etapa. Etapas bloqueadas continuam retornando 403 mesmo que alguém digite a URL diretamente.

## Erros e administração

- 422: associe `errors.campo` ao formulário, inclusive caminhos como `options.0.content`.
- 401: tente a renovação serializada ou volte ao login.
- 403: apresente acesso indisponível; esconder o botão não substitui a policy da API.
- 404: recurso ausente.
- 409: conteúdo congelado por progresso existente ou conflito de unicidade; recarregue o editor.
- 429: respeite `Retry-After` antes de tentar novamente.

No editor, crie trilha e etapas em rascunho, publique aulas/exercícios, depois as etapas e a trilha. Para ordenar, envie todos os IDs do agrupamento para o endpoint `/order`, incluindo rascunhos. Alternativas enviadas em `options` substituem o conjunto inteiro; após salvar, use os novos IDs retornados. A estrutura de uma trilha iniciada fica congelada, mas seus textos e a publicação da própria trilha podem ser atualizados.
