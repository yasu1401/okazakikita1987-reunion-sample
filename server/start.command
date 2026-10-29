#!/bin/bash
cd "$(dirname "$0")" || exit 1
if command -v php >/dev/null 2>&1; then
  REUNION_PHP="$(command -v php)"
elif [ -x ./var/bin/php ]; then
  REUNION_PHP="./var/bin/php"
else
  echo "PHPがありません。README.mdのMac起動手順をご確認ください。"
  read -r -p "Returnで閉じます。"
  exit 1
fi
if [ ! -f ./var/reunion.sqlite ] && [ ! -f ./config.local.php ]; then
  "$REUNION_PHP" scripts/manage.php init || exit 1
  "$REUNION_PHP" scripts/manage.php seed || exit 1
fi
echo "http://127.0.0.1:8790/ を開いてください。終了はControl+Cです。"
"$REUNION_PHP" -d upload_max_filesize=6M -d post_max_size=8M -S 127.0.0.1:8790 -t public public/router.php
