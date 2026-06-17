#!/bin/sh
set -e

# Guarantee exactly ONE Apache MPM at every container start. mod_php requires
# mpm_prefork; if the base image ships with mpm_event/worker also enabled,
# Apache aborts with "AH00534: More than one MPM loaded". Doing this at runtime
# (not build time) makes it immune to stale Docker build-cache layers.
rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf
a2enmod mpm_prefork >/dev/null 2>&1 || true

echo "[entrypoint] MPM modules enabled:"
ls /etc/apache2/mods-enabled/ | grep -i mpm || echo "  (none found)"

exec apache2-foreground
