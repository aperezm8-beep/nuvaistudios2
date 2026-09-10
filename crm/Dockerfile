FROM php:8.2-apache

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Copiar SOLO el contenido de la carpeta crm a la raíz del servidor web
COPY crm/ /var/www/html/

EXPOSE 80