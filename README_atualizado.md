# Sistema de Faturamento

Teste técnico fullstack: sistema de faturamento com autenticação, CRUD de clientes e cobranças, cálculo de juros em tempo real, registro de pagamentos e módulo de relatórios com filtros, paginação, totalizadores e exportação (CSV/PDF).

**Stack:** PHP/Laravel 13 · React (Next.js 16) + TypeScript · MySQL 8.0 · Docker

---

## Como rodar o projeto

Pré-requisitos: Docker e Docker Compose instalados. Nenhuma instalação manual de PHP, Node ou MySQL é necessária.

```bash
# 1. Clonar o repositório e entrar na pasta
git clone <url-do-fork>
cd teste-desenvolvedor-gerador-de-relatorios

# 2. Copiar os arquivos de ambiente
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env

# 3. Subir os containers (mysql, backend, frontend)
docker compose up -d --build

# 4. Gerar a chave da aplicação Laravel (obrigatório, não vem preenchida no .env.example)
docker compose exec backend php artisan key:generate

# 5. As migrations e seeders rodam automaticamente na subida do container backend.
#    Se você gerou a APP_KEY depois da primeira subida, reinicie o backend para
#    garantir que a aplicação carregue a chave nova:
docker compose restart backend

# 6. Acompanhe o log para confirmar que a seed terminou sem erros:
docker compose logs -f backend
```

Quando o log mostrar `Finished: X bills, Y payments.`, o ambiente está pronto.

- **Frontend:** http://localhost:3000
- **Backend (API):** http://localhost:8000
- **MySQL:** localhost:3306

### Login de teste

O sistema não tem cadastro público — o usuário é criado via seeder:

```
E-mail: admin@admin.com
Senha: admin123
```

### Rodando os testes automatizados (backend)

```bash
docker compose exec backend php artisan test
```

Os testes rodam em SQLite em memória (configurado em `phpunit.xml`), isolados do banco MySQL de desenvolvimento — rodar a suíte não afeta os dados do seed.

### Resetando os dados de teste

```bash
docker compose exec backend php artisan migrate:fresh --seed
```

---

## Decisões técnicas

### Nomenclatura em inglês
Todo o schema (tabelas, models, services) usa nomenclatura em inglês (`clients`, `billings`, `payments`) por consistência e por ser convenção mais comum em código Laravel.

### Regra de juros: composto
Cobrança vencida e não paga tem valor atualizado calculado **em tempo real**, nunca persistido no `billing`:

```
valor_atualizado = valor_original × (1 + taxa_mensal) ^ (dias_em_atraso / 30)
```

Implementado em `App\Services\InterestService`. Ao registrar um pagamento, o valor dos juros no momento é armazenado no campo `interest_amount` da tabela `payments` como fato histórico **imutável** — nunca recalculado depois, mesmo que o pagamento seja consultado no futuro.

### Autenticação: Sanctum com tokens Bearer
Optamos por tokens Bearer (não SPA cookie-based) por simplicidade, já que frontend e backend rodam em portas/domínios diferentes — evita a complexidade de configurar CORS com cookies stateful. Login com rate limit (`throttle:5,1`) contra força bruta. Registro é fechado; o único usuário nasce via seeder.

### Documento do cliente (CPF/CNPJ)
Sistema aceita tanto CPF (11 dígitos) quanto CNPJ (14 dígitos), armazenados sem máscara (só dígitos) para evitar duplicatas disfarçadas na constraint `unique`. A validação (`App\Rules\ValidDocument` + `App\Services\DocumentValidationService`) verifica formato e tamanho; **não** calcula o dígito verificador oficial — decisão documentada na seção [Melhorias futuras](#melhorias-futuras--pontos-não-concluídos) abaixo.

### Valores monetários
Banco usa `decimal(12,2)` para valores e `decimal(6,4)` para taxa de juros (armazenada como fração, ex: `0.0250` = 2,5% ao mês) — nunca `float`/`double`, para evitar erros de arredondamento. Conversão para `float` só ocorre internamente nos cálculos do `InterestService`, sempre com `round(..., 2)` no resultado final.

### FKs com `restrictOnDelete()`
Relacionamentos entre `clients` → `billings` → `payments` usam `restrictOnDelete()` (não cascade), impedindo apagar um cliente ou cobrança que tenha histórico vinculado — preserva integridade do histórico financeiro. Por isso, os endpoints de exclusão (`destroy`) não foram implementados nos controllers.

---

## Estratégia de performance

O sistema foi projetado considerando tabelas com potencial de milhões de registros. Os seeders geram um volume de teste de ~72 mil cobranças (30 mil pagas, 20 mil vencidas, 20 mil em dia) e 2 mil clientes, com 30 mil pagamentos associados.

### Índices criados e por quê

**`clients`**
- `is_active` — filtro comum de listagem (clientes ativos/inativos)
- `name` — usado em buscas por nome (autocomplete do frontend, filtro de listagem)

**`billings`**
- `client_id` — toda consulta de cobranças de um cliente específico passa por aqui (FK já indexada automaticamente, mas explicitada)
- `[status, due_date]` (composto) — cobre o padrão de consulta mais comum do sistema: "cobranças pendentes vencidas em um período". Segue a regra de *leftmost prefix*, então também acelera filtros isolados por `status`
- `issue_date` — suporta o filtro de relatório por base temporal "emissão"
- `[client_id, status]` (composto) — acelera consultas de "cobranças de um cliente por status", comum na tela de detalhe do cliente

**`payments`**
- `billing_id` — toda consulta de pagamentos de uma cobrança específica
- `payment_date` — suporta o filtro de relatório por base temporal "pagamento"

### Paginação, filtros e ordenação

Todas as listagens (clientes, cobranças, relatório) usam `paginate()` do Eloquent — nunca `get()` sozinho para exibir listas. Filtros são aplicados via `WHERE`/`whereDate` diretamente na query, antes da paginação, garantindo que o banco filtra os dados (não a aplicação). Ordenação é restrita a uma allowlist de colunas por endpoint, evitando SQL injection via parâmetro de ordenação livre.

### Evitando N+1

Relacionamentos usados em listagens (`client` em `billings`, `payments` na exportação) são sempre carregados via eager loading (`with(...)`), nunca lazy-loaded dentro de um loop. Isso é particularmente crítico na exportação CSV, que itera sobre potencialmente dezenas de milhares de registros via `cursor()` — sem eager loading, cada linha dispararia uma query adicional ao banco.

### Comportamento do relatório com milhões de registros

- **Listagem paginada**: escala bem, já que só a página atual (ex: 15-50 registros) é carregada e processada em PHP para calcular `updated_amount`.
- **Totalizadores**: atualmente somam **todos** os registros que atendem ao filtro (não só a página atual, pois totalizadores precisam refletir o conjunto inteiro), trazendo-os via `->get()` e somando em PHP. Isso funciona bem no volume de teste (~72 mil registros), mas **não escalaria para volumes na casa de milhões** sem otimização adicional — ver detalhes na seção [Melhorias futuras](#melhorias-futuras--pontos-não-concluídos) abaixo.

### Comportamento da exportação com grande volume

- **CSV**: implementado com streaming real via `cursor()` do Eloquent (um registro por vez do banco, sem carregar a coleção inteira em memória) combinado com `response()->streamDownload()` do Laravel (a resposta HTTP começa a ser enviada ao navegador enquanto o arquivo ainda está sendo gerado). Memória utilizada permanece praticamente constante independente do volume de dados exportado.
- **PDF**: gerado via `barryvdh/laravel-dompdf` a partir de uma view Blade. Diferente do CSV, o `dompdf` precisa montar o HTML completo em memória antes de renderizar o PDF — não há streaming real nessa abordagem, e a geração fica lenta/impraticável com milhares de linhas. Por isso, o endpoint de exportação PDF aplica um **teto de 2.000 linhas**: se o filtro retornar mais registros que o limite, a API responde com erro 422 orientando a refinar os filtros ou usar CSV, que suporta qualquer volume. Alternativas para produção estão detalhadas na seção [Melhorias futuras](#melhorias-futuras--pontos-não-concluídos) abaixo.

---

## Melhorias futuras / pontos não concluídos

Decisões técnicas tomadas conscientemente durante o desenvolvimento, além de melhorias que seriam recomendadas para um ambiente de produção. A ideia é deixar claro o que foi simplificado de propósito e por quê, em vez de esconder trade-offs.

### Segurança

**Expiração de token do Sanctum**
Hoje os tokens de API não expiram (`config/sanctum.php`, chave `expiration` está `null`). Em produção, o recomendado é definir um TTL:

```php
'expiration' => 60 * 24, // 24 horas, em minutos
```

Isso obriga reautenticação periódica e reduz o risco de um token vazado permanecer válido indefinidamente.

**Revogação de tokens antigos no login**
Atualmente, cada login gera um novo token sem invalidar os anteriores — um usuário pode acumular múltiplos tokens válidos simultaneamente. Para produção, valeria revogar tokens antigos no momento do login (ou oferecer um endpoint de "sair de todos os dispositivos"):

```php
$user->tokens()->delete();
```

**Validação de documento (CPF/CNPJ)**
Por decisão consciente, a validação de `document` no `ValidDocument` verifica apenas **formato e tamanho** (11 ou 14 dígitos, rejeitando sequências repetidas), sem calcular o dígito verificador oficial de CPF/CNPJ. O enunciado do teste não exige explicitamente essa validação — o campo mínimo pedido é apenas "documento". Uma versão futura poderia implementar o algoritmo completo de dígito verificador no `DocumentValidationService`.

### Consistência de dados

**Transação atômica no registro de pagamento**
O `PaymentController::store()` executa duas escritas separadas (criação do `Payment` e atualização do `status` do `Billing` para `paid`) sem envolvê-las em uma transação de banco. Em caso de falha entre as duas operações (ex.: queda do servidor), é possível ficar com um pagamento registrado mas a cobrança ainda como `pending`. Correção recomendada:

```php
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($billing, $request, $interestAmount) {
    $payment = Payment::create([...]);
    $billing->update(['status' => 'paid']);
});
```

### Performance / grandes volumes

**Cache de totalizadores do relatório**
Os totalizadores do relatório (quantidade, valor original, juros, valor atualizado, recebido, pendente) são recalculados a cada requisição. Para relatórios acessados com frequência sobre o mesmo período/filtros, um cache de curta duração (ex.: Redis, TTL de poucos minutos) reduziria carga no banco.

**Cálculo de totalizadores em PHP vs. SQL**
Atualmente, o `ReportController::calculateTotals()` busca todos os registros que atendem ao filtro (sem paginação, pois totalizadores precisam somar o conjunto inteiro) e calcula os valores de juros/atualizado iterando em PHP. Isso funciona bem no volume de teste (~72 mil cobranças), mas não escala para milhões de registros, pois carrega todas as linhas filtradas para a aplicação antes de somar.

Para produção com volumes muito maiores, a melhoria recomendada é mover o cálculo de juros compostos para dentro da query SQL (usando `POW()`/`DATEDIFF()` do MySQL) e usar `SUM()` agregado no banco, evitando trazer os registros individuais para a aplicação. Alternativa complementar: pré-computar e cachear totalizadores por período mais utilizados.

**Exportação PDF em volumes muito grandes**
A exportação CSV usa streaming real via cursor do banco (`cursor()` + `streamDownload()`), com memória praticamente constante independente do volume. A exportação PDF, por depender do `dompdf` (que renderiza a partir de uma string HTML completa), precisa montar todas as linhas em memória antes de gerar o arquivo — não há streaming real de PDF nessa abordagem, e a geração fica lenta/trava com milhares de linhas.

**Decisão implementada:** o endpoint de exportação PDF aplica um teto de linhas (atualmente 2.000). Se o filtro aplicado retornar mais registros que o limite, a API responde com erro 422 orientando o usuário a refinar os filtros (ex: por cliente ou período menor) ou usar a exportação CSV, que suporta qualquer volume. Essa é uma limitação conhecida e documentada, não um bug.

Para produção com necessidade real de PDFs grandes, as alternativas recomendadas seriam: gerar o PDF de forma assíncrona (fila/job), processando em lotes e notificando o usuário quando o arquivo estiver pronto; ou trocar a biblioteca de renderização por uma com suporte a streaming/paginação nativa.

### Controle de acesso

Atualmente existe apenas um nível de usuário autenticado (sem perfis/papéis). Um sistema de faturamento real normalmente precisaria de ao menos dois níveis (ex.: administrador vs. operador), com permissões diferentes para ações sensíveis como registrar pagamento ou editar cobrança.

### Testes

- Cobertura de testes automatizados está concentrada nos cenários mínimos exigidos pelo enunciado (autenticação, cálculo de juros, filtros, totalizadores, pagamento, exportação). Testes de frontend não foram priorizados nesta entrega.
- Não há testes de carga/performance formais sobre os endpoints com grande volume de dados — a estratégia de índices e paginação foi validada manualmente com os volumes gerados pelos seeders (~72 mil cobranças).

---

## Testes automatizados

Cobertura dos cenários mínimos exigidos:

- [x] Bloqueio de usuário não autenticado (relatório, exportação, clientes, cobranças)
- [x] Cálculo de juros para cobrança vencida (composto)
- [x] Cobrança paga não acumula juros
- [x] Cobrança ainda não vencida não acumula juros
- [x] Filtros do relatório (por status, por cliente)
- [x] Totalizadores do relatório
- [x] Registro de pagamento (com congelamento de `interest_amount`)
- [x] Bloqueio de pagamento duplicado em cobrança já paga
- [x] Exportação CSV
- [x] Exportação PDF (dentro do limite de linhas)

```bash
docker compose exec backend php artisan test
```

Testes de frontend não foram priorizados nesta entrega.

---

## Uso de agentes de IA

Este projeto foi desenvolvido com apoio de assistente de IA (Claude) para geração de código, debugging e revisão de decisões técnicas — incluindo backend, frontend, testes e esta documentação.

O repositório contém arquivos `AGENTS.md`/`CLAUDE.md` dentro de `frontend/`, mas eles **não são configuração de agente autoral do projeto**: são gerados automaticamente pelo próprio `next dev` (Next.js 16) para avisar assistentes de IA sobre mudanças de API entre versões do framework, e são recriados a cada execução do servidor de desenvolvimento. Nenhuma instrução customizada de orientação a agentes foi criada além desta seção do README.

---

## Estrutura do repositório

```
backend/           Laravel 13 — API REST
  app/
    Http/
      Controllers/  Clients, Billings, Payments, Reports, Export, Auth
      Requests/     FormRequests com validação (Store/Update por recurso)
    Models/         Client, Billing, Payment, User
    Rules/          ValidDocument (validação de CPF/CNPJ)
    Services/       InterestService, DocumentValidationService, BillingReportService
  database/
    migrations/
    factories/
    seeders/
  tests/
    Unit/           InterestServiceTest
    Feature/        AuthenticationTest, PaymentTest, ReportTest, ExportTest

frontend/           Next.js 16 (App Router) + TypeScript
  app/
    login/
    (protected)/    clients, billings, reports — protegidos por layout com checagem de auth
  components/       ClientModal, BillingModal, PaymentModal, ClientAutocomplete
  context/          AuthContext (estado de autenticação global)
  lib/              api.ts (cliente HTTP centralizado com Bearer token)

docker-compose.yml  mysql, backend, frontend
README.md
```