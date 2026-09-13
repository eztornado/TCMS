# TornadoCMS (TCMS) — Arquitectura

CMS base modular (Laravel 13 API + React 19 / Mantine 8 admin) pensado como punto de
partida para nuevos proyectos: el código de cada cliente vive separado del core.

## Decisiones de diseño

1. **Roles**: Spatie real (`HasRoles` + middleware `permission:` + caché), sin columna `users.role`.
   Rol `Super Admin` con comodín `*` vía `Gate::before`.
2. **Auditoría**: spatie/laravel-activitylog sobre todos los modelos de dominio (`LogsActivity` en
   `BaseModel`) + eventos de auth. Consulta con filtros y diff old/new. Sin SQL crudo.
3. **Custom Models**: esquema activo (tabla real por modelo, creada por `Schema` builder — sin SQL
   crudo) + metadatos con tipos de campo ricos, validación declarada, estados editoriales, soft
   deletes, taxonomías y caché de definición. Field types: `text, textarea, richtext, number,
   decimal, boolean, date, datetime, select, multiselect, media, relation, repeater, slug, color, json`.
4. **Eventos** (core): `events` + `event_sessions` (múltiples pases con aforo propio) +
   `bookings` con estados y contador de plazas (`capacity − reservas activas`).
5. **Ecommerce**: checkout transaccional con **reserva de stock** (`lockForUpdate` + tabla
   `stock_reservations` con TTL), snapshot de líneas de pedido, máquina de estados con historial,
   numeración `TC-YYYY-######`, cupones reales, métodos de envío, impuestos por país, y
   `PaymentGateway` abstraído (Stripe cuando hay claves; `manual` en desarrollo). El precio SIEMPRE
   se calcula en el servidor (`CartService::quote`), nunca en el cliente.
6. **Auth**: Sanctum SPA por cookies (httpOnly) en vez de JWT en localStorage.
7. **Front**: TanStack Query de verdad, guard de rutas por **permiso**, DataTable genérica con
   estado en URL, y CRUD de custom models dirigido por `schema`.
8. **Menú del panel**: definido en backend (`MenuRegistry`) y filtrado por permisos; el front no
   decide qué se puede ver.

## Estructura

```
TCMS/
├── api/        Backend Laravel 13 (Sanctum, Spatie Permission/ActivityLog/MediaLibrary)
│   └── app/
│       ├── Enums/           OrderStatus, BookingStatus, CustomFieldType...
│       ├── Http/Controllers/
│       │   ├── Admin/       users, roles, audit, media, settings, dashboard, models, events, shop
│       │   ├── Cm/          CRUD genérico de custom models (entries + schema)
│       │   ├── Auth/        login/logout/me/menu/password
│       │   └── Store/       API pública de tienda/eventos (storefront headless)
│       ├── Models/          BaseModel (LogsActivity) + dominio
│       ├── Services/        CustomModels, Media, Cart, Checkout, Stock, Payments, Menu
│       └── Support/         PermissionRegistry, ApiResponse
├── front/      React 19 + Mantine 8 (Vite, TanStack Query, Zustand, react-router 7)
└── docs/       Esta documentación
```

## Endpoints principales

```
POST /api/auth/login | /logout     GET /api/auth/me | /menu
GET  /api/admin/dashboard
CRUD /api/admin/users  /roles  /media  /settings       GET /api/admin/audit
CRUD /api/admin/models (+ POST /api/admin/models/{slug}/fields)     ← builder de custom models
GET  /api/cm/{slug}/schema    CRUD /api/cm/{slug}[/{id}]             ← entries genéricos
CRUD /api/admin/events (+ /sessions)  /bookings
CRUD /api/admin/products (+ /variants)  /orders (PATCH status)  /coupons
GET  /api/cart/quote  POST /api/cart/items  PATCH/DELETE     (carrito server-side por uuid)
POST /api/store/checkout   POST /api/webhooks/{provider}
GET  /api/store/products  /events   POST /api/store/bookings
```

## Convención de permisos

`{verbo}-{recurso}` en kebab-case: `list-users`, `create-users`, `edit-users`, `delete-users`,
`manage-settings`, `list-audit`, y por custom model: `list-cm-{slug}`, `create-cm-{slug}`,
`edit-cm-{slug}`, `delete-cm-{slug}`. Roles semilla: `Super Admin (*), Admin, Editor,
Gestor de tienda, Gestor de eventos`.

## Puesta en marcha

```bash
cd TCMS/api && composer install && cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed && php artisan serve
cd ../front && npm install && npm run dev        # http://localhost:5173
```

Usuario demo: `admin@example.com` / valor de `ADMIN_PASSWORD` (rol Super Admin).
