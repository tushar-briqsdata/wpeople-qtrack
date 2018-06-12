#!/bin/sh
echo "Deleting Cache..."
rm -rf app/cache/prod/*
echo "Cache Deleted..."
php app/console swfworkflows:activity --domain WSuite --tasklist default& echo $! > /var/www/html/activity.pid