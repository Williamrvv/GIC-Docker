#!/bin/bash

# 1. Guardar las variables de entorno de Docker en un archivo que Cron pueda leer
# Esto toma todo lo que hay en el .env y lo pone disponible para el sistema
env >> /etc/environment

# 2. Iniciar el servicio de Cron
service cron start

# 3. Iniciar Apache (Comando original)
exec docker-php-entrypoint apache2-foreground