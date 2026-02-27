FROM alpine:latest

# Install PHP, Nginx and curl
RUN apk add --no-cache php84 php84-fpm php-curl nginx

# Create web directory
RUN mkdir -p /var/www/localhost/htdocs

# Copy files
COPY --chown=nginx:nginx api.php /var/www/localhost/htdocs/
COPY default.conf /etc/nginx/http.d/

# Create startup script
RUN echo -e "#!/bin/sh\nphp84-fpm -D\nnginx -g 'daemon off;'" > /start.sh && chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
