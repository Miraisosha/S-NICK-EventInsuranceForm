#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 3 ]]; then
  echo "Usage: activate-release.sh <app-root> <public-dir> <release-id>" >&2
  exit 2
fi

app_root=$1
public_dir=$2
release_id=$3
release_dir="$app_root/releases/$release_id"

case "$app_root" in
  /home/*/apps/*) ;;
  *) echo "Unsafe APP_ROOT: $app_root" >&2; exit 2 ;;
esac
case "$public_dir" in
  /home/*/public_html/*) ;;
  *) echo "Unsafe PUBLIC_DIR: $public_dir" >&2; exit 2 ;;
esac
[[ "$release_id" =~ ^[0-9a-f]{40}$ ]] || { echo "Invalid release id" >&2; exit 2; }
[[ -d "$release_dir/api" && -d "$release_dir/public" ]] || { echo "Incomplete release" >&2; exit 1; }

mkdir -p "$app_root/shared/config" "$app_root/shared/logs" "$app_root/shared/tmp" "$public_dir"

if [[ -f "$app_root/shared/config/app_local.php.next" ]]; then
  mv "$app_root/shared/config/app_local.php.next" "$app_root/shared/config/app_local.php"
fi
[[ -f "$app_root/shared/config/app_local.php" ]] || { echo "Missing app_local.php" >&2; exit 1; }
chmod 600 "$app_root/shared/config/app_local.php"

ln -sfn "$app_root/shared/config/app_local.php" "$release_dir/api/config/app_local.php"
ln -sfn "$app_root/shared/logs" "$release_dir/api/logs"
ln -sfn "$app_root/shared/tmp" "$release_dir/api/tmp"

cd "$release_dir/api"
/usr/bin/php -r "exit(extension_loaded('sodium') ? 0 : 1);" || { echo "PHP sodium extension is required" >&2; exit 1; }
/usr/bin/php bin/cake.php migrations migrate

if [[ -e "$public_dir/api" && ! -L "$public_dir/api" ]]; then
  echo "Refusing to replace non-symlink path: $public_dir/api" >&2
  exit 1
fi

rsync -a --delete --exclude='/api' --exclude='/.well-known' "$release_dir/public/" "$public_dir/"
ln -sfn "$release_dir/api/webroot" "$public_dir/api"

echo "$release_id" > "$app_root/current-release"
