# TornadoCMS (TCMS)

CMS base a medida que corre en **tres targets con un único proyecto Laravel**:

| Target | Cómo | Estado |
|---|---|---|
| **Web** | API + panel servidos por Docker (Coolify), MySQL + Redis | El despliegue histórico, intacto |
| **Escritorio** | [NativePHP desktop](https://nativephp.com) (Electron + PHP embebido, SQLite local) | `nativephp/desktop` v2 |
| **Móvil** | NativePHP mobile (iOS/Android + PHP embebido, SQLite local) | `nativephp/mobile` v3 |

El panel es **React 19 + Mantine 8**. En web lo sirve nginx (SPA estática con proxy
`/api`); en las apps nativas lo sirve **el propio Laravel embebido** (mismo origen =
cookies Sanctum sin cambios). Las apps nativas funcionan **offline** con SQLite y se
**sincronizan** contra el servidor central cuando hay red (arquitectura híbrida).

Pensado para usarse como **repositorio base** de nuevos proyectos: código de cliente
separado del core, actualizable, dockerizado para **Coolify**.

## Estructura

```
TCMS/
├── api/                    # Backend Laravel (core) — ÚNICO proyecto, 3 targets
│   ├── app/
│   │   ├── Enums/          # OrderStatus, BookingStatus, CustomFieldType...
│   │   ├── Http/           # Controllers/{Auth,Admin,Cm,Store,Sync,Ui}, Middleware
│   │   ├── Models/         # BaseModel audita todo vía spatie/activitylog
│   │   ├── Services/       # Shop, Cms, Media, ★ Sync (motor de sincronización)
│   │   ├── Console/        # ★ native:prepare-env, sync:run/status/resync
│   │   ├── Providers/      # AppServiceProvider + ★ NativeAppServiceProvider
│   │   └── Support/        # ★ Runtime (¿web/desktop/móvil?), Schedule, Sync
│   ├── Modules/            # ★ CÓDIGO DE PROYECTO (nunca actualizado por el core)
│   ├── database/migrations # esquema core (+ uuid y sync_*)
│   ├── routes/api.php      # contrato API completo (+ /api/sync/*)
│   ├── routes/web.php      # ★ sirve el panel embebido cuando existe public/ui
│   ├── config/nativephp.php# identidad del binario, exclusiones del bundle
│   ├── .env.native         # overlay de entorno para el build de escritorio
│   ├── .env.mobile         # overlay para el build móvil
│   └── public/ui/          # build del panel para apps nativas (gitignored)
├── front/                  # Panel React + Mantine (core)
│   └── src/custom/         # ★ CÓDIGO DE PROYECTO (rutas, páginas, servicios)
│   └── src/features/sync/  # indicador de sincronización del panel nativo
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
- **Media** — biblioteca única con miniaturas WebP (GD), hash sha256 del fichero,
  pivot polimórfica, control de "en uso".
- **Infra web** — Sanctum SPA por cookies, caché Redis, colas, scheduler, backups listos para spatie/backup.
- **Infra nativa** — SQLite en WAL, drivers file/database, pagos siempre en el central
  (`TCMS_PAYMENT_GATEWAY=manual` en el binario), scheduler por catch-up al arrancar.

## Apps nativas (NativePHP)

Un solo proyecto Laravel compila a los tres targets. Los paquetes son **mutuamente
excluyentes en composer**: el repo lleva `nativephp/desktop`; para el build móvil se
usa un checkout donde `nativephp/mobile` lo sustituye (más su propio provider).

```bash
cd api

# Desarrollo de escritorio (arranca Electron + Vite con HMR)
composer run native:dev

# Build de instaladores (AppImage/deb/exe/dmg según plataforma)
#   - respalda tu .env de desarrollo en .env.backup
#   - instala el overlay .env.native (genera APP_KEY nativa estable en .env.native.build)
#   - compila el panel React a api/public/ui y lo empaqueta
#   - al terminar restaura tu .env de desarrollo
composer run build:native

# Prepara/restaura el .env a mano
php artisan native:prepare-env            # antes del build
php artisan native:prepare-env --restore  # tras un build interrumpido
```

Detalles que importan:

- **Nunca hay secretos en el binario**: `config/nativephp.php` limpia `*_SECRET`,
  `STRIPE_*`, `MAIL_*`, etc. del `.env` empaquetado. El checkout de pago ocurre
  siempre en el central.
- **El runtime lo decide `TCMS_RUNTIME`** (`web` | `native-desktop` | `native-mobile`)
  y se consulta SIEMPRE vía `App\Support\Runtime` — nunca `env()` sueltos. Un valor
  desconocido se interpreta como `web` (fallo seguro: el central es quien captura el
  log de sincronización).
- `native:run` / `native:build` auto-aplican migraciones al actualizar la app
  instalada: **las migraciones nuevas no pueden ser destructivas**.

## Sincronización (central ⇄ dispositivos)

La app nativa trabaja contra su SQLite y sincroniza por deltas con el central:

- **Identidad**: toda tabla syncable tiene columna `uuid` (los `id` enteros son
  locales de cada base y nunca viajan). Las FK viajan como uuid de la fila destino.
- **Pull**: el central mantiene `sync_log` (append-only, cursor por device en
  `devices.last_pull_cursor`). Los pivots de spatie viajan como snapshot completo.
- **Push**: los cambios locales se encolan en `sync_outbox` (misma transacción que
  el cambio) y el central los aplica con **LWW** (last-write-wins): solo gana el
  device si su `updated_at` es estrictamente más reciente; si no, gana el central
  y el choque queda en `sync_conflicts`.
- **Borrados**: los soft-deletes viajan como upsert con `deleted_at`; solo el
  forceDelete genera tombstone (`sync_tombstones`).
- **Media**: la metadata va por el protocolo normal (tabla bidi); los **bytes** por
  un canal propio (`POST /api/sync/media/check|{uuid}`, `GET .../download`) guiado
  por el hash sha256.
- **Online-only en v1**: comercio transaccional (orders, carts, payments, bookings,
  stock). El checkout ocurre en el central.
- **UI**: el panel nativo muestra un indicador con pendientes y botón
  "Sincronizar ahora" (`front/src/features/sync`).

Para añadir una tabla syncable: migra la columna `uuid`, añade la entrada en
`App\Support\Sync\SyncRegistry` (modelo + política `pull`/`bidi` + refs) y deja que
`SyncRegistryTest` valide que el manifiesto sigue siendo coherente.

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

# Panel embebido (probar la SPA servida por Laravel, como en nativo)
cd front && BUILD_TARGET=native npm run build   # → api/public/ui
cd ../api && php artisan serve                   # http://localhost:8000/
```

Login por defecto (seed): `admin@example.com` / valor de `ADMIN_PASSWORD` (`cambia-esto-ya`).

> Ajusta en `api/.env`: `SANCTUM_STATEFUL_DOMAINS=localhost:5173` y `SESSION_DOMAIN=localhost`.

## Producción (Coolify)

Ver **[docs/COOLIFY.md](docs/COOLIFY.md)**. Resumen: recurso *Docker Compose*
apuntando a este repo; define `APP_KEY`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`,
`SANCTUM_STATEFUL_DOMAINS`; el primer despliegue siembra roles/menú/admin solo.
El central es también el punto de sincronización: los devices se enlazan con
`POST /api/auth/device` (desde el panel) o `POST /api/auth/device/login` (con
credenciales) y operan con `POST /api/sync/pull|push`.

## Crear un proyecto nuevo sobre esta base

Ver **[docs/BASE-WORKFLOW.md](docs/BASE-WORKFLOW.md)**. Resumen:

1. Crea un repositorio nuevo desde este (plantilla o fork) → `origin` = tu proyecto, `upstream` = esta base.
2. El código propio va SOLO en `api/Modules/<TuProyecto>` y `front/src/custom/`.
3. `git pull upstream main` trae mejoras del core sin tocar tu código.

## Tests

```bash
cd api && php artisan test        # 116 tests, 477 aserciones (Unit + Feature)
cd front && npm run build         # build de verificación del panel
```

### Por qué ahora son más importantes

El mismo código Laravel corre hoy **en tres runtimes distintos**: servidor web con
MySQL/Redis y binarios nativos con SQLite embebido que **auto-migran al
actualizarse**. El protocolo de sincronización cruza motores de base de datos
diferentes, redes intermitentes y ediciones concurrentes con resolución LWW. Ningún
desarrollador ejercita eso a mano: **la suite es la única especificación ejecutable
del sistema**. Romperla no es "un test rojo": es un device que pierde datos o un
central que deja de propagar cambios.

Dos invariantes que la suite protege y que conviene tener presentes al tocar código:

1. **La web sigue funcionando byte a byte.** Toda Feature suite corre sobre sqlite
   `:memory:` con el mismo contrato HTTP; si algo del core cambia de comportamiento,
   los tests originales (auth, auditoría, CM, eventos, tienda) lo detectan.
2. **El sync siempre converge.** `SyncPullTest`, `SyncPushTest`, `SyncConflictTest`,
   `SyncSoftDeleteTest` y `SyncDynamicTest` ejercitan el protocolo completo con el
   gateway *loopback* (central y device en el mismo proceso, sin red). Al simular
   "dos bases" en una sola, los tests **retrodatan el `updated_at` del central**
   (`forceFill(...)->saveQuietly()`) antes del push: sin eso el LWW vería timestamps
   iguales y daría conflicto espurio. Respeta ese patrón en tests nuevos.

Estructura de la suite:

| Suite | Cubre |
|---|---|
| `Unit/RuntimeTest` | Contexto de ejecución (web/desktop/móvil, desconocido ⇒ web) |
| `Unit/SyncPayloadTest` | Serialización ida/vuelta del protocolo (FK ⇄ uuid) |
| `Unit/SyncRegistryTest` | Integridad del manifiesto de tablas syncables |
| `Feature/SpaControllerTest` | Panel embebido, fallback de rutas, traversal, prefijos reservados |
| `Feature/DeviceTokenTest` | Alta/revocación de devices y tokens Sanctum `ability:sync` |
| `Feature/SyncPullTest` | Deltas, cursor, pivots, paginación, sin eco propio, endpoint HTTP |
| `Feature/SyncPushTest` | Outbox → central, tombstones con origen, tablas pull-only rechazadas |
| `Feature/SyncConflictTest` | LWW determinista y convergencia del device |
| `Feature/SyncSoftDeleteTest` | Soft delete ⇄ upsert, forceDelete ⇄ delete+tombstone |
| `Feature/SyncDynamicTest` | Tablas `cm_*`: materialización, evolución de esquema, destrucción |
| `Feature/SyncCommandsTest` | `sync:run/status/resync` |
| `Feature/SyncMediaHttpTest` | Canal de ficheros de media por HTTP |
| `Feature/SyncStateEndpointTest` | Endpoints locales del indicador del panel |
| (originales) | Auth, roles, auditoría, custom models, eventos, tienda |

Reglas que la suite ya cazó una vez y que no hay que volver a romper:

- **Los listeners de eventos de Eloquent no devuelven nada** (cuerpos con bloque,
  no flechas): un listener que devuelve `false` corta la cadena y el observer de
  sync dejaría de registrar cambios silenciosamente.
- **`App\Support\Runtime` es la única fuente de contexto**; un runtime desconocido
  debe caer en `web`.
- **Migraciones no destructivas**: el binario nativo las auto-aplica al actualizar.
- Antes de subir: `cd api && vendor/bin/pint --dirty && vendor/bin/phpunit`.
