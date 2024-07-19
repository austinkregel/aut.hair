FROM austinkregel/base:latest

RUN setcap "cap_net_bind_service=+ep" /usr/bin/php8.2

COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /etc/php/8.2/cli/conf.d/99-sail.ini
RUN chmod +x /usr/local/bin/start-container

ENTRYPOINT ["start-container"]
