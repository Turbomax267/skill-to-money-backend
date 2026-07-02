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

## Setup con Docker

Este repositorio puede levantarse de forma independiente con Docker.

Pasos:

1. Copiar `.env.example` a `.env`
2. Completar en ese `.env` las credenciales reales de PostgreSQL Render, Resend y Peru API
3. Construir la imagen:

```bash
docker build -t skill-to-money-backend .
```

4. Levantar el contenedor:

```bash
docker run --env-file .env -p 8000:80 skill-to-money-backend
```

URLs:

```txt
Backend: http://localhost:8000
Health: http://localhost:8000/api/health
```

## Docker para desarrollo diario

Si quieres trabajar sin borrar contenedores ni reconstruir la imagen por cada cambio, usa el modo dev:

Primera vez:

```bash
docker compose -f docker-compose.dev.yml up --build
```

Uso diario:

```bash
docker compose -f docker-compose.dev.yml up
```

Detener:

```bash
docker compose -f docker-compose.dev.yml down
```

Con este modo:

- el codigo local se monta dentro del contenedor
- los cambios en `app`, `routes`, `config` y demas archivos del backend se reflejan sin rebuild
- `vendor` queda persistido en un volumen Docker
- no se ejecutan migraciones automaticamente

Solo reconstruye si cambias:

- `composer.json`
- `composer.lock`
- `Dockerfile`
- extensiones PHP o configuracion base del contenedor

Si cambias dependencias y quieres reinstalarlas:

```bash
docker compose -f docker-compose.dev.yml down
docker compose -f docker-compose.dev.yml up --build
```

## Variables de entorno

Variables clave para Render:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-render-service.onrender.com
FRONTEND_URL=https://your-lovable-app.lovable.app
CORS_ALLOWED_ORIGINS=https://your-lovable-app.lovable.app,http://localhost:5173
PUBLIC_DISK_ROOT=/var/data/skill-to-money-public

DB_CONNECTION=pgsql
DB_HOST=your-render-postgres-host
DB_PORT=5432
DB_DATABASE=your-render-postgres-database
DB_USERNAME=your-render-postgres-user
DB_PASSWORD=your-render-postgres-password
DB_SSLMODE=require

MAIL_MAILER=resend
MAIL_FROM_ADDRESS=onboarding@resend.dev
MAIL_FROM_NAME="Skill To Money"
RESEND_API_KEY=re_xxxxxxxxx
```

## Almacenamiento persistente para imagenes

Las imagenes del portafolio y de perfil no se guardan dentro de la base de datos. La base solo guarda la ruta, y el archivo real se almacena en el disco `public`.

Ruta usada por defecto:

```txt
storage/app/public
```

Si quieres que en produccion las imagenes no se pierdan al hacer redeploy, configura un disco persistente en Render y define:

```env
PUBLIC_DISK_ROOT=/var/data/skill-to-money-public
```

Con eso:

- las imagenes nuevas se guardaran en el disco persistente
- el backend servira esos archivos por la ruta `GET /api/media/{path}`
- el contenedor ya no dependera de que el archivo exista dentro de su filesystem efimero

Importante:

- `portfolio_projects.image_path` guarda solo rutas como `portfolio/images/archivo.png`
- la imagen real debe existir dentro de `PUBLIC_DISK_ROOT/portfolio/images`
- si la base tiene la ruta pero el archivo no existe en ese disco, la imagen no se podra mostrar

No subir `.env` real al repositorio.

## Correo con Resend

El backend ya queda preparado para usar Resend como proveedor de correo en Render.

Pasos:

1. Crear una cuenta en Resend.
2. Generar una API key.
3. Configurar estas variables en Render:

```env
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=onboarding@resend.dev
MAIL_FROM_NAME="Skill To Money"
RESEND_API_KEY=re_xxxxxxxxx
```

4. Hacer redeploy del backend.

Notas:

- Ya no es necesario usar `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` ni `MAIL_SCHEME` cuando `MAIL_MAILER=resend`.
- Para produccion, lo ideal es verificar un dominio propio en Resend y reemplazar `onboarding@resend.dev` por un remitente de tu dominio.

## Correo con Gmail via Google Apps Script

Si el backend corre en Render free y necesitas enviar desde una cuenta Gmail sin usar SMTP, puedes usar un webhook de Google Apps Script.

Variables necesarias:

```env
MAIL_MAILER=google_apps_script
MAIL_FROM_ADDRESS=skill.to.money.262@gmail.com
MAIL_FROM_NAME="Skill To Money"
GOOGLE_MAIL_WEBHOOK_URL=https://script.google.com/macros/s/TU_WEBAPP/exec
GOOGLE_MAIL_WEBHOOK_SECRET=tu_secreto_compartido
GOOGLE_MAIL_WEBHOOK_TIMEOUT=15
```

Con este modo:

- el correo de bienvenida y el de recuperacion de contrasena se envian por HTTPS al webhook
- Render no necesita salida SMTP
- el webhook es quien finalmente manda el correo desde Gmail

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
