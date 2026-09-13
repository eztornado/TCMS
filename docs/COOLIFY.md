# Desplegar TornadoCMS en Coolify

TCMS es un único `docker-compose.yml` que Coolify orquesta directamente:
`api` (nginx+php-fpm), `queue` (colas Redis), `scheduler` (cron de Laravel),
`front` (SPA estática con proxy al API), `mysql` y `redis`.

## 1. Crear el recurso

1. En tu proyecto de Coolify: **+ New Resource → Docker Compose**.
2. Conecta el repositorio (branch `main`) y deja que Coolify lea el
   `docker-compose.yml` de la raíz.
3. Coolify detecta los servicios; el único puerto publicado es el del
   `front` (`FRONT_PORT`, por defecto 8080) — así todo sale por el mismo
   dominio y las cookies de Sanctum funcionan sin CORS.
4. En **Domains**, mapea tu dominio al puerto del front y activa HTTPS.

## 2. Variables de entorno (Settings → Environment)

| Variable | Obligatoria | Ejemplo | Nota |
|---|---|---|---|
| `APP_KEY` | ✅ | `base64:...` | `php artisan key:generate --show` en local |
| `DB_PASSWORD` | ✅ | (secreto) | usuario `tcms` |
| `DB_ROOT_PASSWORD` | ✅ | (secreto) | solo para el healthcheck/root |
| `SANCTUM_STATEFUL_DOMAINS` | ✅ | `panel.midominio.com` | dominio del front SIN protocolo, con puerto si no es 443 |
| `APP_URL` | ✅ | `https://panel.midominio.com` | URL pública final |
| `FRONT_URL` | ✅ | `https://panel.midominio.com` | igual que APP_URL si todo sale por el front |
| `SESSION_DOMAIN` | — | `.midominio.com` | solo si API y front van en subdominios distintos |
| `STRIPE_SECRET` | — | `sk_live_...` | sin él se usa la pasarela `manual` |
| `STRIPE_WEBHOOK_SECRET` | — | `whsec_...` | webhook: `https://.../api/webhooks/stripe` |
| `TCMS_PAYMENT_GATEWAY` | — | `auto` | `auto` (Stripe si hay claves, si no manual) · `stripe` · `manual` |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | 1er deploy | `admin@...` | credenciales iniciales del seed |
| `MAIL_MAILER` + credenciales | — | `resend` | producción debe enviar de verdad |

## 3. Primer despliegue

El entrypoint del API hace automáticamente, en orden:

1. Espera a MySQL.
2. `php artisan migrate --force` (idempotente en cada deploy).
3. `php artisan storage:link`.
4. Solo la primera vez (0 usuarios): `db:seed` → roles, permisos, menú,
   ajustes y usuario admin.
5. Cachea config/rutas y arranca nginx + php-fpm.

Después del primer arranque: entra en el panel y **cambia la contraseña del
admin**. Puedes borrar `ADMIN_PASSWORD` de las variables.

## 4. Webhook de Stripe

Añade un endpoint en Stripe → Developers → Webhooks:

```
https://panel.midominio.com/api/webhooks/stripe
```

Eventos: `checkout.session.completed`, `charge.refunded`.
La confirmación del pago SIEMPRE llega por webhook (idempotente por
`event.id`); el redirect de vuelta es solo UX.

## 5. Backups

- **BD**: activa en Coolify los backups programados del servicio MySQL
  (S3 compatible o local) — diario recomendado.
- **Media**: volume `api-storage` (ficheros subidos). Inclúyelo en tu
  estrategia (o activa `spatie/laravel-backup` en un módulo de proyecto).

## 6. Actualizar el core o desplegar tu proyecto

- Cada `git push` a la rama configurada en Coolify dispara el build y el
  `migrate --force` del entrypoint (rollout sin pasos manuales).
- Para actualizar el CORE desde la base en tu proyecto:
  `git fetch upstream && git merge upstream/main` (ver BASE-WORKFLOW.md).
  Al empujar, Coolify redespliega solo.

## 7. Escalar / notas

- `queue` y `scheduler` son contenedores separados: puedes subir sus
  réplicas sin tocar el API.
- Los volúmenes persistentes son `db-data`, `redis-data` y `api-storage`.
- El API también expone HTTP en su red interna (`api:80`) por si quieres
  publicarlo directamente con otro dominio (recuerda entonces CORS +
  `SANCTUM_STATEFUL_DOMAINS`).
