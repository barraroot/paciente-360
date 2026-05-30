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

exec /usr/local/bin/start-container "$@"
