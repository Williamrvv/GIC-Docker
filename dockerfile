FROM php:8.2-apache

# 1. Instalar drivers de MySQL (PDO y MySQLi)
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable pdo_mysql

# 2. Activar módulos necesarios de Apache
# (Activamos rewrite para URLs limpias y ssl para HTTPS)
RUN a2enmod rewrite ssl