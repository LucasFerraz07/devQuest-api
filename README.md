# DevQuest API

API Laravel 13 para uma plataforma de estudos com trilhas, aulas em Markdown, exercícios de múltipla escolha, progresso, XP e conquistas. Autenticação JWT e autorização por policies com os papéis `admin` e `student`.

## Preparação

Requisitos: PHP 8.3+, Composer e SQLite com `pdo_sqlite`. O front-end React é um projeto separado; Node não é necessário para executar esta API.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Configure `ADMIN_EMAIL` e `ADMIN_PASSWORD` antes de executar os seeders. Os padrões locais são `admin@teste.com` / `password`. Os seeders são repetíveis e não redefinem a senha de usuários existentes.

Para adicionar a trilha demonstrativa **Fundamentos de JavaScript**, com duas etapas:

```bash
php artisan db:seed --class=DemoContentSeeder
```

Este seeder não altera uma trilha demonstrativa já existente. As conquistas iniciais e o administrador são criados por `DatabaseSeeder`; a trilha de demonstração é opcional.

## Configuração

| Variável | Padrão | Uso |
| --- | --- | --- |
| `FRONTEND_URLS` | `http://localhost:5173` | Origens CORS, separadas por vírgula |
| `JWT_SECRET` | Gerado por `jwt:secret` | Assinatura dos tokens |
| `JWT_TTL` | `60` | Validade do token de acesso, em minutos |
| `JWT_REFRESH_TTL` | `20160` | Janela de renovação desde a emissão original, em minutos |
| `XP_PER_LEVEL` | `100` | XP necessário por nível |
| `CACHE_STORE` | `database` | Armazenamento compartilhado para blacklist JWT e rate limits |

O SQLite usa transações `IMMEDIATE` e espera de até 5 segundos por bloqueios. Escritas em progresso e conteúdo bloqueiam a trilha e depois o usuário, nessa ordem. Em bancos com bloqueio por linha, o serviço usa `FOR UPDATE`. Outro banco de produção precisa ser incluído na validação antes da implantação.

## Contrato HTTP

A base é `/api`. Envie `Accept: application/json`, `Content-Type: application/json` nas escritas e `Authorization: Bearer TOKEN` nas rotas autenticadas.

- [OpenAPI 3.1](docs/openapi.json): contratos completos de entrada e saída, filtros e respostas.
- [Integração com React](docs/react-integration.md): fluxo demonstrativo e consumo dos resultados.

Sucesso: `{ "error": false, "message": "", "data": ... }`. Listagens acrescentam `meta` no mesmo nível de `data`, com `current_page`, `per_page`, `total` e `last_page`. `per_page` aceita de 1 a 100. O catálogo usa 12 itens por padrão; administração e históricos usam 20.

Erros: `{ "error": true, "message": "...", "data": null }`. Validação retorna 422 e acrescenta `errors`, com mensagens agrupadas por campo. A API também padroniza 401, 403, 404, 409, 429 e 500.

UUIDs são strings; datas usam ISO 8601 UTC. Os recursos do aluno nunca incluem `is_correct`. A explicação aparece apenas depois do primeiro acerto daquele aluno.

## Regras de estudo

A primeira etapa publicada de cada trilha fica disponível. A próxima exige concluir a anterior. Iniciar etapa e concluir aula são idempotentes; consultas não criam progresso. Responder ou concluir uma aula também inicia a etapa automaticamente.

Concluir uma etapa exige todas as aulas publicadas concluídas, todos os exercícios publicados respondidos pelo menos uma vez e nota maior ou igual a `minimum_score` (0–100):

```text
nota = floor(100 × pontos dos exercícios já acertados / pontos totais)
nível = floor(XP total / XP_PER_LEVEL) + 1
```

Um acerto anterior continua contando após uma resposta errada. Novas tentativas ficam no histórico, mas apenas o primeiro acerto concede os pontos do exercício como XP. A primeira resposta correta, a primeira etapa concluída e a primeira trilha concluída concedem conquistas únicas.

O cliente marca a leitura de uma aula explicitamente; a API não mede tempo de leitura. As aulas são Markdown e devem ser renderizadas com sanitização no React.

## Gestão de conteúdo

Administradores usam CRUD em `/api/admin/learning-paths`, `/stages`, `/lessons` e `/exercises`. Use `PATCH /{id}` com `is_published` para publicar ou despublicar.

Crie primeiro a trilha e suas etapas em rascunho. Depois crie aulas e exercícios publicados, publique a etapa e, por fim, publique a trilha. Publicar uma etapa exige ao menos uma aula e um exercício válidos publicados. O exercício de múltipla escolha exige duas ou mais alternativas e exatamente uma correta, mesmo em rascunho.

O campo `options` substitui o conjunto inteiro de alternativas e gera novos IDs. Não envie IDs nas alternativas de entrada. Os identificadores de pai (`learning_path_id` e `stage_id`) são imutáveis após a criação.

A reordenação aceita todos os IDs do agrupamento, incluindo rascunhos, na ordem desejada:

```text
PUT /api/admin/learning-paths/{id}/stages/order
PUT /api/admin/stages/{id}/lessons/order
PUT /api/admin/stages/{id}/exercises/order

{ "ids": ["UUID-DO-SEGUNDO", "UUID-DO-PRIMEIRO"] }
```

Depois que um aluno inicia a trilha, ordem, composição, pontos, gabaritos, critérios e publicação individual dos itens ficam bloqueados. Correções editoriais e a publicação/despublicação da trilha continuam disponíveis. Exclusões que destruiriam progresso retornam 409; despublicar preserva o histórico.

Exercícios de código podem ser armazenados como rascunhos administrativos, mas não publicados nem submetidos. Recuperação de senha, ranking, execução de código e versionamento de conteúdo ficam fora desta versão.

## Testes

```bash
composer test
```

Os testes usam SQLite em memória, chaves exclusivas de teste e cache em memória. O teste de concorrência usa um arquivo temporário SQLite e dois processos independentes, removidos ao final. Nenhum teste usa o banco configurado para desenvolvimento.

A suíte cobre autenticação, renovação de token expirado, publicação, autorização, progressão, isolamento entre alunos, XP, conquistas, reordenação, concorrência, rollback e consistência entre rotas e OpenAPI.
