# Skill To Money API

Backend Laravel para el Sprint 1 de Skill To Money. Esta API esta preparada para desplegarse en Render, conectarse a Supabase PostgreSQL y ser consumida por el frontend en Lovable.

## Arquitectura

El flujo obligatorio del Sprint 1 es:

```txt
Controller -> Service -> Repository -> Database
```

Responsabilidades:

- `app/Http/Controllers/Api`: recibe requests, llama servicios y retorna JSON.
- `app/Services`: contiene logica de negocio y orquestacion.
- `app/Repositories`: contiene consultas y persistencia.
- `app/Contracts`: interfaces para services y repositories.
- `app/Providers/AppServiceProvider.php`: bindings de interfaces a implementaciones.

La respuesta JSON estandar es:

```json
{
  "success": true,
  "message": "Message",
  "data": {},
  "errors": null
}
```

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

Variables principales:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-render-service.onrender.com
FRONTEND_URL=https://your-lovable-app.lovable.app
CORS_ALLOWED_ORIGINS=https://your-lovable-app.lovable.app,http://localhost:5173

DB_CONNECTION=pgsql
DB_HOST=aws-0-us-east-1.pooler.supabase.com
DB_PORT=6543
DB_DATABASE=postgres
DB_USERNAME=postgres.your-project-ref
DB_PASSWORD=your-supabase-password
DB_SSLMODE=require
```

## Render

Build command:

```bash
composer install --no-dev --optimize-autoloader && php artisan config:cache && php artisan route:cache
```

Start command:

```bash
php artisan serve --host=0.0.0.0 --port=$PORT
```

Health check:

```txt
GET /health
```

## Endpoints Sprint 1

Health:

```txt
GET /health
GET /api/health
```

Auth:

```txt
POST /api/auth/register/freelancer
POST /api/auth/register/mype
POST /api/auth/login
POST /api/auth/forgot-password
POST /api/auth/logout
```

Perfil:

```txt
GET /api/profile
POST /api/profile
PUT /api/profile
PATCH /api/profile
PATCH /api/profile/skills
POST /api/profile/photo
PATCH /api/profile/description
PATCH /api/profile/social-links
```

Los endpoints protegidos usan:

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

Registro MYPE:

```json
{
  "first_name": "Luis",
  "last_name": "Torres",
  "company_name": "Lumen Cafe",
  "email": "luis@example.com",
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

Crear/editar perfil:

```json
{
  "headline": "Disenadora grafica",
  "category": "diseno",
  "bio": "Branding para MYPEs",
  "description": "Creo marcas visuales claras para negocios digitales.",
  "location": "Lima, PE",
  "hourly_rate": 90,
  "skills": ["Branding", "Logos", "Social Media"],
  "photo_url": "https://example.com/photo.jpg"
}
```

Redes sociales:

```json
{
  "social_links": {
    "linkedin": "https://linkedin.com/in/camila",
    "instagram": "https://instagram.com/camila",
    "website": "https://camila.dev"
  }
}
```

## Checklist Sprint 1

- `GET /health` responde `{"status":"ok"}`.
- `GET /api/health` responde `{"status":"ok"}`.
- CORS permite Lovable y localhost.
- Supabase usa `pgsql` con `DB_SSLMODE=require`.
- Auth registra freelancer y MYPE.
- Auth login devuelve `access_token`.
- Logout revoca el token actual.
- Perfil permite crear, editar, habilidades, foto, descripcion y redes.
- README y `.env.example` no contienen secretos reales.

## Validacion

```bash
php artisan migrate
php artisan test
```
