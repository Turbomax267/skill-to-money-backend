# Skill To Money API

Backend Laravel para Skill To Money. El backend se despliega en Render, usa PostgreSQL en Render y es consumido por el frontend en Lovable.

## Estado actual

Esta version deja una base limpia para crecer por modulos:

```txt
Auth
Users
Profiles
Catalog
Marketplace
Messaging
Recommendations
Health
```

Auth custom queda funcional usando tokens Bearer en la tabla `api_tokens`.

## Estructura principal

```txt
app/Http/Controllers/Api
app/Http/Middleware
app/Http/Requests
app/Http/Responses
app/Models
app/Repositories
app/Services
database/migrations
routes/api.php
routes/web.php
```

Controllers actuales:

```txt
AuthController
UsersController
ProfilesController
CatalogController
MarketplaceController
MessagingController
RecommendationController
HealthController
```

## Base de datos

Tablas principales:

```txt
users
api_tokens
password_reset_tokens
freelancer_profiles
mype_profiles
skills
freelancer_skills
categories
services
portfolio_projects
favorites
conversations
messages
notifications
recommendations
matches
market_trends
```

`freelancer_profiles` incluye `dni` como `VARCHAR(20) NULL`.

## Setup local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

API local:

```txt
http://127.0.0.1:8000
```

## Variables de entorno

Variables clave para Render:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-render-service.onrender.com
FRONTEND_URL=https://your-lovable-app.lovable.app
CORS_ALLOWED_ORIGINS=https://your-lovable-app.lovable.app,http://localhost:5173

DB_CONNECTION=pgsql
DB_HOST=your-render-postgres-host
DB_PORT=5432
DB_DATABASE=your-render-postgres-database
DB_USERNAME=your-render-postgres-user
DB_PASSWORD=your-render-postgres-password
DB_SSLMODE=require
```

No subir `.env` real al repositorio.

## Render

Si se despliega con Docker, Render usa el `Dockerfile`.

El contenedor ejecuta:

```bash
php artisan migrate --force && php artisan config:cache && apache2-foreground
```

Health checks:

```txt
GET /health
GET /api/health
```

## Endpoints actuales

Publicos:

```txt
GET /health
GET /api/health
POST /api/auth/register
POST /api/auth/register/freelancer
POST /api/auth/register/mype
POST /api/auth/login
POST /api/auth/forgot-password
```

Protegidos con Bearer token:

```txt
POST /api/auth/logout
GET /api/users
GET /api/profiles
GET /api/catalog
GET /api/marketplace
GET /api/messaging
GET /api/recommendations
```

Header requerido:

```txt
Authorization: Bearer {access_token}
```

## Ejemplos

Registro freelancer:

```json
{
  "first_name": "Camila",
  "last_name": "Rojas",
  "email": "camila@example.com",
  "password": "password123"
}
```

Login:

```json
{
  "email": "camila@example.com",
  "password": "password123"
}
```

Respuesta de Auth:

```json
{
  "success": true,
  "message": "Session started.",
  "data": {
    "token_type": "Bearer",
    "access_token": "token",
    "expires_at": "date",
    "user": {
      "id": 1,
      "name": "Camila Rojas",
      "email": "camila@example.com",
      "user_type": "freelancer",
      "account_type": "freelancer"
    }
  },
  "errors": null
}
```

## Validacion

```bash
php artisan migrate
php artisan test
```

## Checklist

```txt
[ ] Configurar APP_URL en Render
[ ] Configurar CORS_ALLOWED_ORIGINS con dominio Lovable
[ ] Configurar PostgreSQL de Render
[ ] Confirmar GET /health
[ ] Confirmar POST /api/auth/register/freelancer
[ ] Confirmar POST /api/auth/login
[ ] Confirmar POST /api/auth/logout
```
