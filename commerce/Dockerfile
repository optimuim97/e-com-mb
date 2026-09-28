# ══════════════════════════════════════════════════════════════════════
# Boutique — image applicative (PHP-FPM 8.3 + Laravel)
#
# Sert à la fois php-fpm, les workers de queue, Reverb et le scheduler :
# c'est la commande passée au container qui change, pas l'image.
# ══════════════════════════════════════════════════════════════════════
FROM php:8.3-fpm-alpine

# ── Dépendances système ───────────────────────────────────────────────
RUN apk add --no-cache \
    git curl zip unzip bash \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    libzip-dev oniguruma-dev \
    icu-dev linux-headers \
    autoconf g++ make \
    ffmpeg mysql-client

# ── Extensions PHP ────────────────────────────────────────────────────
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install \
    pdo_mysql mbstring exif pcntl bcmath gd zip opcache intl

RUN pecl install redis \
 && docker-php-ext-enable redis

# ── OPcache : réglages production ─────────────────────────────────────
# Le code ne change jamais dans une image → pas besoin de vérifier les
# timestamps à chaque requête.
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.validate_timestamps=0'; \
      echo 'opcache.memory_consumption=192'; \
      echo 'opcache.max_accelerated_files=20000'; \
      echo 'opcache.interned_strings_buffer=16'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# ── Limites PHP (uploads d'images produits) ───────────────────────────
RUN { \
      echo 'upload_max_filesize=25M'; \
      echo 'post_max_size=25M'; \
      echo 'memory_limit=512M'; \
    } > /usr/local/etc/php/conf.d/app.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ── Code + dépendances ────────────────────────────────────────────────
# Installation en un seul temps : les modules nwidart sont découverts par
# les scripts post-install, qui ont besoin de l'application complète.
COPY . .
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist

# ── Permissions ───────────────────────────────────────────────────────
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
