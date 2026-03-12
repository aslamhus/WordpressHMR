# syntax=docker/dockerfile:1
FROM wordpress:latest
# install composer outside public dir
WORKDIR /var/www
RUN curl -s https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer
# copy package.json to config directory and install aslamhus/wordpress-hmr
RUN touch ../composer.json
RUN composer require aslamhus/wordpress-hmr
# reset working directory (public wordpress dir)
WORKDIR /var/www/html