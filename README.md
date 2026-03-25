# Backend Autentica FashionF

Backend PHP + MySQL para Render.

## Rotas iniciais
- `GET /api/health`
- `GET /api/health/db`
- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/admin/login`
- `GET /api/auth/me`
- `GET /api/dashboard/summary`
- `GET /api/categories`
- `POST /api/categories`
- `GET /api/products`
- `POST /api/products`
- `GET /api/customers/addresses`
- `POST /api/customers/addresses`
- `GET /api/orders`
- `POST /api/orders`
- `POST /api/uploads/cloudinary/sign`

## Senha admin
Crie a hash com PHP e insira em `admins`.

## Subida
- importe `sql/schema.sql`
- configure variáveis do `.env.example`
- faça deploy no Render
