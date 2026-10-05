# Local stand-in for Wedos NoLimit: Apache + PHP 8.2 with GD, .htaccess enabled.
FROM php:8.2-apache
RUN apt-get update && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" gd exif \
 && rm -rf /var/lib/apt/lists/* \
 && a2enmod rewrite headers deflate \
 && sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
 && printf 'upload_max_filesize=16M\npost_max_size=20M\nmemory_limit=256M\n' > /usr/local/etc/php/conf.d/uploads.ini
