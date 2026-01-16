#!/bin/bash
set -e

# Iniciar cron
service cron start

# Iniciar Apache (comando original)
exec docker-php-entrypoint apache2-foreground