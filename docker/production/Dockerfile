FROM ghcr.io/roadrunner-server/roadrunner:latest AS roadrunner

FROM austinkregel/base:latest

COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr
RUN setcap "cap_net_bind_service=+ep" /usr/bin/php8.1

COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /etc/php/8.1/cli/conf.d/99-sail.ini
RUN chmod +x /usr/local/bin/start-container

## Build NPM deps, copy to image
#RUN npm i && npm run build
#COPY public/build /var/www/html/public/build

ENTRYPOINT ["start-container"]
