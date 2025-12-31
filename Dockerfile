FROM austinkregel/base:8.3

WORKDIR /var/www/html
COPY . /var/www/html
RUN rm -f .env storage/*.key storage/*.json storage/*.html
RUN cd /var/www/html && ls -alh && composer install
RUN php artisan optimize:clear
RUN php artisan optimize

RUN apt update && apt install nodejs npm -y
RUN npm install && npm run build && rm -rf node_modules && rm -rf /tmp/*
