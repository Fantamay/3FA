FROM php:8.2-apache

# Extensions PHP nécessaires
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Activer mod_rewrite pour .htaccess
RUN a2enmod rewrite

# Copier la configuration Apache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copier le code source
WORKDIR /var/www/html
COPY . .

# Installer les dépendances PHP (sans dev en prod)
RUN composer install --no-dev --optimize-autoloader

# Permissions sur le dossier uploads
RUN mkdir -p uploads && chown -R www-data:www-data uploads

EXPOSE 80
