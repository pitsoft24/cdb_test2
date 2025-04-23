#!/bin/bash

# Set proper permissions for the debug log file
touch debug.log
chmod 666 debug.log
chown www-data:www-data debug.log

echo "Debug log permissions have been set" 