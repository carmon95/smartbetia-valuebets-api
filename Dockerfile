# Etapa 1: usar Composer para instalar dependencias
FROM composer:2 AS vendor

WORKDIR /app

# Copiamos solo lo necesario para instalar dependencias
COPY composer.json composer.lock ./

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Etapa 2: imagen final con PHP
FROM php:8.2-cli

# Instalar extensiones necesarias para Laravel + PostgreSQL
RUN apt-get update && apt-get install -y \
        libpq-dev git unzip \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copiamos vendor desde la primera etapa
COPY --from=vendor /app/vendor ./vendor

# Copiamos el resto del proyecto
COPY . .

# Caching de configuración y rutas (opcional pero recomendado)
RUN php artisan config:cache || true \
 && php artisan route:cache || true

# Render detecta el puerto, usamos el 8000 con php artisan serve
EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
