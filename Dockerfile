# Etapa 1: usar Composer para instalar dependencias
FROM composer:2 AS vendor

WORKDIR /app

# Copiamos todo el proyecto (respeta lo que ignore .dockerignore)
COPY . .

# Instalamos dependencias de Laravel SIN ejecutar scripts
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts

# Etapa 2: imagen final con PHP 8.4
FROM php:8.4-cli

# Instalar extensiones necesarias para Laravel + PostgreSQL
RUN apt-get update && apt-get install -y \
        libpq-dev git unzip \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copiamos TODO el proyecto ya con vendor desde la etapa anterior
COPY --from=vendor /app .

# 🔹 Crear las carpetas de cache que Laravel necesita
RUN mkdir -p storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
 && chmod -R 775 storage bootstrap/cache

# Exponemos el puerto donde va a escuchar php artisan serve
EXPOSE 8000

# Comando de arranque
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
