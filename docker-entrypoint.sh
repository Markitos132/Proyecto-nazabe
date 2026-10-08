#!/bin/sh
# ═══════════════════════════════════════════════════════════
#  Arranque del contenedor en Render.
# ═══════════════════════════════════════════════════════════
set -e

# Render asigna el puerto por variable de entorno. FrankenPHP lo lee
# desde SERVER_NAME; sin host delante, escucha en todas las interfaces.
export SERVER_NAME=":${PORT:-8080}"

echo "→ BioNEA Organiks arrancando en el puerto ${PORT:-8080}"

# APP_URL mal formada tumba el contenedor entero: Laravel construye una
# Request a partir de ella al arrancar la consola, y si el host no es
# valido falla con "Invalid URI: Host is malformed" antes de ejecutar
# ningun comando. Como es una variable cosmetica (solo afecta las URLs
# absolutas que genera asset()), conviene avisar y seguir, no morir.
case "${APP_URL}" in
    "")
        ;;
    http://*|https://*)
        case "${APP_URL}" in
            *"<"*|*">"*|*" "*)
                echo "!! APP_URL contiene caracteres invalidos: '${APP_URL}'"
                echo "!! Se ignora. Corregila en el entorno del servicio."
                unset APP_URL
                ;;
        esac
        ;;
    *)
        echo "!! APP_URL no empieza con http:// ni https://: '${APP_URL}'"
        echo "!! Se ignora. Corregila en el entorno del servicio."
        unset APP_URL
        ;;
esac

# Los directorios de storage no viajan en git (solo sus .gitignore),
# y el disco de Render es efímero: hay que recrearlos en cada arranque.
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Con SQLite, crear el archivo de la base si no existe (primer arranque).
if [ "${DB_CONNECTION}" = "sqlite" ] && [ -n "${DB_DATABASE}" ] && [ ! -f "${DB_DATABASE}" ]; then
    echo "→ Creando base SQLite en ${DB_DATABASE}"
    mkdir -p "$(dirname "${DB_DATABASE}")"
    touch "${DB_DATABASE}"
fi

# Las migraciones no corren solas por defecto. Aplicarlas en cada deploy
# es cómodo hasta el día que un despliegue automático altera la base de
# producción sin que nadie lo pida. Se activa poniendo
# RUN_MIGRATIONS=true en el entorno del servicio.
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "→ Aplicando migraciones"
    php artisan migrate --force --no-interaction
fi

# Compilar configuración, rutas y vistas. Evita releer y reinterpretar
# decenas de archivos en cada petición.
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "→ Listo"

exec frankenphp run --config /etc/caddy/Caddyfile
