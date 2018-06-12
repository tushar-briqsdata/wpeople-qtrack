#!/bin/sh
set -e
chmod -R 777 /var/www/html/app/cache;
chmod -R 777 /var/www/html/app/logs;
chmod -R 777 "/var/www/html/spool/$WCLIENT_ID";
