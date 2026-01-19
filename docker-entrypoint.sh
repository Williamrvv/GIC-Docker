#!/bin/bash
set -e

# 1. TRUCO: Volcar variables de entorno para que Cron las vea
printenv | grep -v "no_proxy" >> /etc/environment

# 2. Iniciar cron
service cron start

# 3. Iniciar Apache
exec docker-php-entrypoint apache2-foreground