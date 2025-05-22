FROM austinkregel/base:8.2

WORKDIR /var/www/html
COPY . /var/www/html
RUN rm -f .env storage/*.key
RUN cd /var/www/html && ls -alh && composer install
RUN php artisan optimize:clear

RUN apt update && apt install nodejs npm -y
RUN npm install && npm run build && rm -rf node_modules && rm -rf /tmp/*
