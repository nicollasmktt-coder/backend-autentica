# AUTENTICA FASHIONF - Backend PHP para Render

Projeto limpo para subir no Git e fazer deploy no Render com Docker.

## Rotas iniciais

- `GET /api/health`
- `GET /api/health/db`

## Variáveis

Copie `.env.example` para `.env` no ambiente local.
No Render, cadastre as variáveis no painel.

## Subida local com Docker

```bash
docker build -t autentica-api .
docker run --rm -p 10000:10000 --env-file .env autentica-api
```

## Banco

Importe `sql/schema.sql` no seu MySQL para criar a base inicial.
