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

# 4. Configurar el Cron Job
# IMPORTANTE: Usamos un archivo temporal y luego sed para quitar caracteres Windows (\r)
# También forzamos la carga de /etc/environment antes de ejecutar PHP
RUN echo "0 3 * * * root . /etc/environment; /usr/local/bin/php /var/www/html/cron/backup_nas.php >> /var/www/html/cron/nas_sync.log 2>&1\n" > /tmp/cron-temp

# Convertir saltos de línea Windows a Linux (CRLF -> LF) en el archivo cron
RUN sed -i 's/\r$//' /tmp/cron-temp && \
    mv /tmp/cron-temp /etc/cron.d/gic-backup

# Permisos estrictos (Cron requiere esto sí o sí)
RUN chmod 0644 /etc/cron.d/gic-backup

# Registrar el cron
RUN crontab /etc/cron.d/gic-backup

# 5. Script de arranque
COPY docker-entrypoint.sh /usr/local/bin/

# CORRECCIÓN CRÍTICA: Convertir saltos de línea del entrypoint también
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh && \
    chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]