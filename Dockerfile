FROM austinkregel/base:8.3

WORKDIR /var/www/html
COPY . /var/www/html

RUN apt update && apt install -y php8.3-mysql php8.3-sqlite3 php8.3-redis php8.3-gd php8.3-mbstring php8.3-xml php8.3-zip php8.3-pdo php8.3-ldap php8.3-curl

RUN rm -f .env storage/*.key storage/*.json storage/*.html
RUN cd /var/www/html && composer install
RUN php artisan optimize:clear
RUN php artisan optimize

RUN apt update && apt install nodejs npm -y
RUN npm install && npm run build && rm -rf node_modules && rm -rf /tmp/*
