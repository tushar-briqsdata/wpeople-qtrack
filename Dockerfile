FROM 133013689155.dkr.ecr.us-east-1.amazonaws.com/php7.0-apache2:latest

RUN apt-get update && apt-get install -y cron

COPY docker-files/vsites.json /opt/consul-template/vsites.json
COPY docker-files/vsites.ctmpl /opt/consul-template/vsites.ctmpl
COPY docker-files/local-config.ctmpl /opt/consul-template/local-config.ctmpl
COPY docker-files/consul.sh.ctmpl /opt/consul-template/consul.sh.ctmpl
COPY docker-files/service.sh.ctmpl /opt/consul-template/service.sh.ctmpl
COPY docker-files/ssl_certificates_generator.sh.ctmpl /opt/consul-template/ssl_certificates_generator.sh.ctmpl

COPY . /var/www/html/
COPY docker-files/docker-php-entrypoint /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-php-entrypoint
RUN chmod +x /var/www/html/activity.sh

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php
RUN php -r "unlink('composer-setup.php');"
RUN mv composer.phar /usr/local/bin/composer
COPY docker-files/cron.conf /etc/supervisor/conf.d/cron.conf
COPY docker-files/crontab.txt /tmp/crontab.txt
RUN crontab /tmp/crontab.txt

ENTRYPOINT ["docker-php-entrypoint"]
