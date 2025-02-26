#!/bin/bash
if [ ! -f box.phar ]; then
    wget -O box.phar "https://github.com/box-project/box/releases/latest/download/box.phar"
    chmod +x box.phar
fi 