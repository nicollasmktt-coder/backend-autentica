# Backend Autentica FashionF

API PHP + MySQL para Render/Hostinger.

## O que já cobre
- auth de admin e cliente com JWT
- CRUD de categorias, subcategorias, produtos e cupons
- CRUD de endereços do cliente
- listagem de clientes no admin
- criação, listagem e atualização de pedidos
- rastreio detalhado
- dashboard com faturamento e métricas
- assinatura Cloudinary para upload direto do front
- checkout InfinitePay por link integrado
- confirmação de pagamento por redirect e webhook

## Passos
1. Copie `.env.example` para `.env`
2. Preencha variáveis
3. Importe `sql/schema.sql`
4. Crie um admin em `admins`
5. Faça deploy no Render

## Rotas principais
- GET `/api/health`
- GET `/api/health/db`
- POST `/api/auth/register`
- POST `/api/auth/login`
- POST `/api/auth/admin/login`
- GET `/api/auth/me`
- GET `/api/dashboard/summary`
- GET|POST|PATCH|DELETE `/api/categories`
- GET|POST|PATCH|DELETE `/api/subcategories`
- GET|POST|PATCH|DELETE `/api/products`
- POST `/api/products/images/delete`
- GET|POST|PATCH|DELETE `/api/coupons`
- GET `/api/admin/customers`
- GET `/api/admin/customers/show?id=1`
- GET|POST|PATCH|DELETE `/api/customers/addresses`
- GET|POST|PATCH `/api/orders`
- GET|POST `/api/orders/tracking`
- POST `/api/payments/infinitepay/checkout`
- POST `/api/payments/infinitepay/confirm`
- POST `/api/payments/infinitepay/webhook`
- POST `/api/uploads/cloudinary/sign`
