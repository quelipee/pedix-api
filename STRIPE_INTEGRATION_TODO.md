# Stripe Checkout — configuração pendente

## Values to Replace

O projeto já tinha Checkout de assinaturas (cenário A). O modo, os itens e as URLs reais foram preservados. Os identificadores de preço ainda dependem de configuração; não há preços de exemplo inseridos na chamada.

**Arquivos com valores pendentes ou referências à configuração:**

- [.env.example](.env.example): modelo das variáveis; preencha o `.env` local ou as variáveis do servidor.
- [config/billing.php](config/billing.php): resolve os identificadores usados em `line_items[0].price` para cada plano.

| Campo                          | Valor atual                                    | O que configurar                                                          |
| ------------------------------ | ---------------------------------------------- | ------------------------------------------------------------------------- |
| `line_items[0].price` — mensal | `STRIPE_PRICE_MONTHLY` vazio no ambiente local | ID de um preço recorrente ativo de R$ 79,90, em BRL, cobrado a cada mês.  |
| `line_items[0].price` — anual  | `STRIPE_PRICE_YEARLY` vazio no ambiente local  | ID de um preço recorrente ativo de R$ 799,00, em BRL, cobrado a cada ano. |

Crie os preços no [Dashboard da Stripe](https://dashboard.stripe.com/prices), no mesmo ambiente das chaves usadas pela aplicação. Não é necessário substituir o código de `line_items`, `mode`, `success_url` ou `cancel_url`.

## Configured Parameters

**Arquivo que contém os parâmetros:**

- [app/Services/StripeSubscriptionGateway.php](app/Services/StripeSubscriptionGateway.php)

| Parâmetro                         | Valor             |
| --------------------------------- | ----------------- |
| `ui_mode`                         | `hosted_page`     |
| `billing_address_collection`      | `auto`            |
| `phone_number_collection.enabled` | `false`           |
| `automatic_tax.enabled`           | `false`           |
| `allow_promotion_codes`           | `false`           |
| `payment_method_collection`       | `always`          |
| `submit_type`                     | `auto`            |
| `integration_identifier`          | `hosted_web_0003` |
| `origin_context`                  | `web`             |

SDK instalado: `stripe/stripe-php` v21.3.2, que define `hosted_page`. A inicialização do cliente e sua versão de API não foram alteradas.

Conforme a escolha do usuário, foram preservados os parâmetros existentes de cliente/equipe, metadados da assinatura, período grátis, expiração, meios de pagamento e a chave de idempotência. Os 15 dias grátis continuam sendo concedidos à primeira assinatura da equipe; o cartão é coletado no checkout e a cobrança começa após o período grátis.

## Setup and Next Steps

### Configuração do ambiente

1. Preencha `STRIPE_KEY`, `STRIPE_SECRET` e os dois identificadores de preço no `.env` local ou no gerenciador de segredos do servidor. As chaves locais ainda estão vazias. Use inicialmente um ambiente de teste da Stripe.
2. Configure `APP_URL` com a URL da aplicação. Os retornos são gerados pela rota existente `/{current_team}/billing`, com `checkout=success` ou `checkout=cancelled`.
3. Configure o destino de eventos da Stripe para `POST /stripe/webhook` e preencha `STRIPE_WEBHOOK_SECRET` com o segredo desse destino. Use os eventos definidos em `cashier.webhook.events`, em [config/cashier.php](config/cashier.php). Para testes locais, a Stripe CLI pode encaminhar os eventos ao endereço local dessa rota.
4. Ative/configure o portal de clientes no Dashboard da Stripe para gerenciar e cancelar assinaturas.
5. Após alterar variáveis, execute `php artisan config:clear` localmente; na implantação, recrie o cache de configuração conforme o processo do servidor.
6. Em um ambiente novo, instale as dependências com `composer install` e aplique as migrations existentes pelo processo normal de implantação. Este ajuste não criou nem executou migrations e não adicionou dependências.

Mantenha os nomes atuais das variáveis: eles já correspondem aos arquivos de configuração. Não coloque segredos em variáveis `VITE_` nem em arquivos versionados. O checkout hospedado usa redirecionamento e não exige Stripe.js no frontend.

### Estrutura e fluxo existentes

- [app/Http/Controllers/Billing/SubscriptionController.php](app/Http/Controllers/Billing/SubscriptionController.php): recebe a escolha do proprietário e redireciona ao checkout.
- [app/Actions/Billing/CreateSubscriptionCheckout.php](app/Actions/Billing/CreateSubscriptionCheckout.php): controla a contratação e a reutilização de tentativas.
- [app/Services/StripeSubscriptionGateway.php](app/Services/StripeSubscriptionGateway.php): valida o preço configurado e cria a sessão hospedada.
- [routes/web.php](routes/web.php): já contém as rotas de cobrança e webhook autenticado por assinatura da Stripe.
- [resources/js/pages/billing/Index.vue](resources/js/pages/billing/Index.vue): tela de assinatura em Configurações.

O proprietário escolhe o plano em **Configurações → Assinatura**, conclui o checkout hospedado e retorna à aplicação. Os eventos assinados da Stripe sincronizam a assinatura da equipe pelo Laravel Cashier. O retorno de sucesso, sozinho, não confirma a contratação. Esta integração cobra assinaturas do Pedix; ela não recebe pagamentos dos pedidos dos restaurantes.

Nenhum endpoint ou arquivo de aplicação novo foi necessário. O único arquivo novo deste ajuste é este documento.

### Testes antes de ativar cobranças reais

- Execute os testes existentes: `php artisan test --compact tests/Feature/StripeSubscriptionGatewayTest.php tests/Feature/SubscriptionBillingTest.php tests/Feature/StripeWebhookTest.php`.
- No checkout do ambiente de teste, use `4242 4242 4242 4242`, validade futura e CVC de três dígitos. Não use um cartão real. Veja [testes da Stripe](https://docs.stripe.com/testing).
- Confira os dois planos, os 15 dias grátis na primeira contratação, o cancelamento/retorno e o recebimento dos eventos na equipe correta.
- Confira o portal de clientes, as alterações de assinatura e a sincronização de cancelamentos. Use [testes de assinaturas](https://docs.stripe.com/billing/testing) para simular o fim do período grátis e renovações.
- O fluxo completo com a conta Stripe ainda precisa ser validado após configurar as credenciais. Os testes automatizados usam respostas simuladas e não efetuam cobranças.
- Para produção, configure as chaves, os preços e o segredo do webhook do ambiente de produção e use HTTPS. Confirme os valores e as opções do portal antes de liberar a contratação.
- Se os produtos ou preços dos planos mudarem, mantenha os identificadores e os valores esperados em `config/billing.php` coerentes. A sincronização trata assinaturas; acompanhamento de pedidos e pagamentos dos restaurantes continua no fluxo próprio do Pedix.

### Recursos

- [Referência de Checkout Sessions](https://docs.stripe.com/api/checkout/sessions/create)
- [Suporte Stripe](https://support.stripe.com)
- [Stripe MCP](https://docs.stripe.com/mcp)
