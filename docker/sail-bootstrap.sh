#!/bin/sh
# Roda como root dentro do container Sail ANTES do gosu dropar p/ $WWWUSER.
# Conserta a posse das pastas que recebem escrita via bind-mount (`.:/var/www/html`).
#
# Por que existe: se em algum momento alguém rodar build/install dentro do container
# como root (ex.: `sail root-shell`, `docker compose exec -u root`), os arquivos
# gerados ficam `root:root` no host. A partir daí, o user `sail` (UID = $WWWUSER)
# perde permissão de apagar/regerar — `vite build` quebra com EACCES em rmSync,
# `composer dump-autoload` quebra em bootstrap/cache, etc.
#
# Solução: a cada `up`, reasseguramos a posse correta antes de qualquer comando.
# Idempotente, barato (chown só dos paths mutáveis), seguro em re-clones.

WWWUSER="${WWWUSER:-1337}"
WWWGROUP="${WWWGROUP:-$WWWUSER}"

for path in public/build storage bootstrap/cache; do
    target="/var/www/html/$path"
    if [ -d "$target" ]; then
        chown -R "$WWWUSER:$WWWGROUP" "$target" 2>/dev/null || true
    fi
done

# Sinaliza ao Horizon (container irmão) para terminar e reiniciar — único jeito
# de o supervisor recarregar autoload/opcache depois de um `composer install` no
# host. O comando só publica uma flag no Redis; Horizon faz o graceful restart
# sozinho (`restart: unless-stopped` no compose o sobe de volta). Idempotente:
# no 1º boot ainda não há worker rodando e a flag é consumida quando subir.
# Rodamos como $WWWUSER pra não criar cache/log com posse root, e em background
# pra não atrasar o start se o Redis ainda não estiver pronto.
if [ -f /var/www/html/artisan ] && command -v gosu >/dev/null 2>&1; then
    (
        cd /var/www/html \
            && gosu "$WWWUSER" php artisan horizon:terminate >/dev/null 2>&1 || true
    ) &
fi

exec /usr/local/bin/start-container "$@"
