FROM php:8.2-apache

# 1. Instalar dependencias, Cron y Cliente SMB
RUN apt-get update && apt-get install -y \
    cron \
    smbclient \
    libsmbclient-dev \
    && rm -rf /var/lib/apt/lists/*

# 2. Instalar extensiones PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable pdo_mysql

# 3. Activar módulos Apache
RUN a2enmod rewrite ssl

# 4. Configurar el Cron Job (Diario a las 3:00 AM)
RUN echo "0 3 * * * /usr/local/bin/php /var/www/html/cron/backup_nas.php >> /var/log/cron.log 2>&1" > /etc/cron.d/gic-backup
RUN chmod 0644 /etc/cron.d/gic-backup
RUN crontab /etc/cron.d/gic-backup
RUN touch /var/log/cron.log

# 5. Script de arranque
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]