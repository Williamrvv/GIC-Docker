FROM php:8.2-apache

# 1. Instalar dependencias
RUN apt-get update && apt-get install -y \
    cron \
    smbclient \
    libsmbclient-dev \
    && rm -rf /var/lib/apt/lists/*

# 2. Extensiones PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable pdo_mysql

# 3. Apache Mods
RUN a2enmod rewrite ssl

# 4. CONFIGURACIÓN CRON (MODO PRUEBA: CADA MINUTO)
# Cambiamos '0 3 * * *' por '* * * * *' para que corra YA.
# Asegúrate de dejar una línea vacía al final del echo para evitar errores de sintaxis en cron.
RUN echo "0 3 * * * root /usr/local/bin/php /var/www/html/cron/backup_nas.php >> /var/www/html/cron/nas_sync.log 2>&1\n" > /etc/cron.d/gic-backup

# Permisos estrictos (Cron es exigente con esto)
RUN chmod 0644 /etc/cron.d/gic-backup

# Registrar el cron
RUN crontab /etc/cron.d/gic-backup

# 5. Entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]