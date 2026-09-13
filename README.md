# TornadoCMS (TCMS)

CMS base a medida: **Laravel 13** (API + panel headless) y **React 19 + Mantine 8**
(panel admin). Pensado para usarse como **repositorio base** de nuevos proyectos:
código de cliente separado del core, actualizable, dockerizado para **Coolify**.

## Estructura

```
TCMS/
├── api/                    # Backend Laravel (core)
│   ├── app/
│   │   ├── Enums/          # OrderStatus, BookingStatus, CustomFieldType...
│   │   ├── Http/           # Controllers/{Auth,Admin,Cm,Store}, Middleware, Resources
│   │   ├── Models/         # BaseModel audita todo vía spatie/activitylog
│   │   └── Services/       # Shop (cart/checkout/stock/pagos), Cms, Media
│   ├── Modules/            # ★ CÓDIGO DE PROYECTO (nunca actualizado por el core)
│   ├── database/migrations # esquema core
│   └── routes/api.php      # contrato API completo
├── front/                  # Panel React + Mantine (core)
│   └── src/custom/         # ★ CÓDIGO DE PROYECTO (rutas, páginas, servicios)
├── docker/                 # Dockerfiles (api nginx+fpm, front nginx)
├── docker-compose.yml      # api + queue + scheduler + front + mysql + redis
└── docs/
```

## Funciones del core

- **Roles y permisos** — spatie/permission, convención `{list,show,create,edit,delete,manage}-{recurso}`,
  menú del panel servido ya filtrado (`GET /api/auth/menu`), guards de ruta en front y middleware en API.
- **Auditoría** — todos los modelos de dominio registran created/updated/deleted con
  usuario, diff old/new, IP; consulta y filtros en `/admin/audit`.
- **Custom Models** — entidades dinámicas estilo WordPress con esquema ACTIVO
  (tabla física real por entidad, no EAV): builder en `/content`, campos con
  tipo/validación/visibilidad, taxonomías jerárquicas o planas, API genérica `/api/cm/{modelo}`.
- **Eventos** — eventos con sesiones (pases), aforo y plazas reales,
  inscripciones (`bookings`) con estados y referencia `EV-AAAA-XXXXXX`.
- **Ecommerce** — productos con variantes, carrito server-side por uuid
  (invitados incluidos), quote único en servidor (subtotal/descuento/IVA/envío),
  checkout en una transacción con lock y reserva de stock, cupones con límites,
  pasarela abstraída (Stripe incluido; `manual` como alternativa), webhooks
  idempotentes, máquina de estados de pedido con historial, numeración `TC-AAAA-NNNNNN`.
- **Media** — biblioteca única con miniaturas WebP (GD), pivot polimórfica, control de "en uso".
- **Infra** — Sanctum SPA por cookies, caché Redis, colas, scheduler, backups listos para spatie/backup.

## Desarrollo local

```bash
# Backend
cd api
cp .env.example .env
php /ruta/a/composer.phar install
php artisan key:generate
php artisan migrate --seed        # sqlite por defecto
php artisan serve                 # http://localhost:8000

# Front
cd front
npm install
npm run dev                       # http://localhost:5173 (proxy /api → :8000)
```

Login por defecto (seed): `admin@example.com` / valor de `ADMIN_PASSWORD` (`cambia-esto-ya`).

> Ajusta en `api/.env`: `SANCTUM_STATEFUL_DOMAINS=localhost:5173` y `SESSION_DOMAIN=localhost`.

## Producción (Coolify)

Ver **[docs/COOLIFY.md](docs/COOLIFY.md)**. Resumen: recurso *Docker Compose*
apuntando a este repo; define `APP_KEY`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`,
`SANCTUM_STATEFUL_DOMAINS`; el primer despliegue siembra roles/menú/admin solo.

## Crear un proyecto nuevo sobre esta base

Ver **[docs/BASE-WORKFLOW.md](docs/BASE-WORKFLOW.md)**. Resumen:

1. Crea un repositorio nuevo desde este (plantilla o fork) → `origin` = tu proyecto, `upstream` = esta base.
2. El código propio va SOLO en `api/Modules/<TuProyecto>` y `front/src/custom/`.
3. `git pull upstream main` trae mejoras del core sin tocar tu código.

## Tests

```bash
cd api && php artisan test        # auth, roles, auditoría, CM, eventos, tienda
cd front && npm run build         # build de verificación
```
