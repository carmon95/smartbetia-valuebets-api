# Etapa 1: usar Composer para instalar dependencias
FROM composer:2 AS vendor

WORKDIR /app

# Copiamos todo el proyecto (excepto lo que ignora .dockerignore)
COPY . .

# Instalamos dependencias de Laravel SIN ejecutar scripts (para evitar llamar a artisan aquí)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts

# Etapa 2: imagen final con PHP
FROM php:8.2-cli

# Instalar extensiones necesarias para Laravel + PostgreSQL
RUN apt-get update && apt-get install -y \
        libpq-dev git unzip \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copiamos TODO el proyecto ya con vendor desde la etapa anterior
COPY --from=vendor /app . 

# Exponemos el puerto donde va a escuchar php artisan serve
EXPOSE 8000

# Comando de arranque
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
