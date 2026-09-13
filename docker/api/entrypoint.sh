#!/bin/sh
# Entrypoint del API: espera la BD, ejecuta migraciones y arranca el proceso.
set -e

ROLE=${CONTAINER_ROLE:-app}

echo "» Esperando a MySQL en $DB_HOST:${DB_PORT:-3306}..."
until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); echo "ok"; } catch (Throwable) { exit(1); }' > /dev/null 2>&1; do
  sleep 2
done
echo "» MySQL disponible."

case "$ROLE" in
  app)
    # Migraciones idempotentes; seed solo la primera vez (cuando no hay users).
    php artisan migrate --force --no-interaction
    php artisan storage:link --no-interaction || true

    USER_COUNT=$(php artisan tinker --execute='echo App\Models\User::count();' 2>/dev/null | tail -1)
    if [ "$USER_COUNT" = "0" ]; then
      echo "» Primera ejecución: sembrando roles, menú, ajustes y admin..."
      php artisan db:seed --force --no-interaction
      echo "» Admin inicial: ${ADMIN_EMAIL:-admin@example.com} (cambia la contraseña al entrar)."
    fi

    php artisan config:cache
    php artisan route:cache
    php artisan event:cache

    exec "$@"
    ;;
  queue|scheduler)
    php artisan config:cache
    exec "$@"
    ;;
  *)
    exec "$@"
    ;;
esac
