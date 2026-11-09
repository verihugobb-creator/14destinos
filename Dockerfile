# Imagen del servidor web: PHP 8.2 + Apache (como el hosting)
FROM php:8.2-apache

# pdo_mysql: para que PHP pueda hablar con MySQL
RUN docker-php-ext-install pdo_mysql

# Módulos de Apache que usa el .htaccess (rewrite = las rutas /api/...)
RUN a2enmod rewrite headers expires deflate

# Permitir que los .htaccess funcionen (por defecto Apache los ignora)
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
