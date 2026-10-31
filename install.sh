#!/usr/bin/env bash
# OpenMeru Mail installer.
#
#   curl -fsSL https://openmeru.com/install | bash
#   curl -fsSL https://openmeru.com/install | bash -s -- --dir ~/openmeru-app --branch main
#
# Run it as the Linux user that owns the site (on CloudPanel, the site user).
# It checks the requirements, downloads or updates the code, installs the PHP
# dependencies and starts the interactive setup (php artisan openmeru:install).
# Run it again later to update: it pulls, installs and migrates, keeping .env.
set -euo pipefail

REPO="${OPENMERU_REPO:-https://github.com/WeslleyHarakawa/OpenMeru.git}"
BRANCH="${OPENMERU_BRANCH:-main}"
DIR="${OPENMERU_DIR:-$HOME/openmeru-app}"
SETUP_ARGS=()

while [ $# -gt 0 ]; do
  case "$1" in
    --dir) DIR="$2"; shift 2 ;;
    --branch) BRANCH="$2"; shift 2 ;;
    --repo) REPO="$2"; shift 2 ;;
    --no-setup) NO_SETUP=1; shift ;;
    -h|--help)
      sed -n '2,11p' "$0" 2>/dev/null || true
      echo "Options: --dir PATH  --branch NAME  --repo URL  --no-setup  (anything else is passed to php artisan openmeru:install)"
      exit 0 ;;
    *) SETUP_ARGS+=("$1"); shift ;;
  esac
done

bold()  { printf '\033[1m%s\033[0m\n' "$*"; }
ok()    { printf '  \033[32m✔\033[0m %s\n' "$*"; }
fail()  { printf '  \033[31m✖\033[0m %s\n' "$*" >&2; }
die()   { fail "$*"; exit 1; }

# "curl | bash" leaves stdin on the pipe; the setup asks questions, so reopen the terminal
# when there is one. Without a terminal (CI, ssh without -t) it runs non-interactively.
case " ${SETUP_ARGS[*]:-} " in *" --no-interaction "*|*" -n "*) ;; *)
  if [ ! -t 0 ] && { exec 3</dev/tty; } 2>/dev/null; then
    exec <&3 3<&-
  fi ;;
esac

echo
bold "OpenMeru Mail installer"
echo

# ---- 1. requirements -------------------------------------------------------
bold "Checking requirements"
[ "$(id -u)" = "0" ] && fail "You are root. Run this as the site user (the one that owns the web root), not as root." && exit 1

# OpenMeru needs PHP 8.5. Servers with several versions (CloudPanel, Debian/Ubuntu with
# the sury packages) ship them as php8.5, php8.6, ...: pick the newest one that qualifies,
# even when the plain "php" is older.
PHP=""
for candidate in ${OPENMERU_PHP:-} php8.7 php8.6 php8.5 php; do
  [ -n "$candidate" ] || continue
  if command -v "$candidate" >/dev/null 2>&1 && "$candidate" -r 'exit(version_compare(PHP_VERSION, "8.5.0", ">=") ? 0 : 1);' 2>/dev/null; then
    PHP="$(command -v "$candidate")"; break
  fi
done
[ -n "$PHP" ] || die "PHP 8.5 or newer was not found (checked php8.5 and php). Install it, e.g. https://php.new/install/linux/8.5, or set OPENMERU_PHP=/path/to/php8.5"
PHP_VERSION="$("$PHP" -r 'echo PHP_VERSION;')"
ok "PHP $PHP_VERSION ($PHP)"

missing=""
for ext in pdo_mysql mbstring openssl dom fileinfo curl ctype tokenizer xml; do
  "$PHP" -m 2>/dev/null | grep -qi "^$ext$" || missing="$missing $ext"
done
[ -n "$missing" ] && die "Missing PHP extensions:$missing. On Debian/Ubuntu: sudo apt install$(for e in $missing; do printf ' php8.5-%s' "${e/pdo_/}"; done)"
ok "PHP extensions"

COMPOSER="$(command -v composer 2>/dev/null || true)"
[ -n "$COMPOSER" ] || die "composer is not installed. See https://getcomposer.org/download/ (then run this script again)."
ok "Composer $("$PHP" "$COMPOSER" --version 2>/dev/null | awk '{print $3}')"

# git is the nicest way to get and update the code, but not required: without it the
# release package (a tarball from the same repository) is downloaded instead.
GIT="$(command -v git 2>/dev/null || true)"
[ -n "$GIT" ] && ok "git" || ok "git not installed: using the release package"
TARBALL="${OPENMERU_TARBALL:-${REPO%.git}/archive/refs/heads/$BRANCH.tar.gz}"

# Downloads the package and copies it over $DIR, keeping this install's own files
# (.env, storage, vendor). A fresh $DIR simply gets everything.
unpack_release() {
  local work; work="$(mktemp -d)"
  curl -fsSL "$TARBALL" -o "$work/release.tar.gz" || die "Could not download $TARBALL"
  mkdir -p "$work/tree"
  tar -xzf "$work/release.tar.gz" -C "$work/tree"
  local root; root="$(find "$work/tree" -mindepth 1 -maxdepth 1 -type d | head -n 1)"
  [ -f "$root/artisan" ] || die "The package does not look like OpenMeru."
  mkdir -p "$DIR"
  if command -v rsync >/dev/null 2>&1; then
    rsync -a --exclude .env --exclude .git --exclude storage --exclude vendor --exclude node_modules --exclude bootstrap/cache "$root/" "$DIR/"
  else
    (cd "$root" && find . -type d ! -path './storage*' ! -path './vendor*' ! -path './.git*' -exec mkdir -p "$DIR/{}" \; \
      && find . -type f ! -name .env ! -path './storage/*' ! -path './vendor/*' ! -path './.git/*' -exec cp -p "{}" "$DIR/{}" \;)
  fi
  rm -rf "$work"
}

# ---- 2. code ---------------------------------------------------------------
echo
if [ -f "$DIR/artisan" ]; then
  bold "Updating $DIR"
  if [ -d "$DIR/.git" ] && [ -n "$GIT" ]; then
    "$GIT" -C "$DIR" fetch -q origin "$BRANCH"
    "$GIT" -C "$DIR" checkout -q "$BRANCH"
    "$GIT" -C "$DIR" pull -q --ff-only origin "$BRANCH"
    ok "Code updated (branch $BRANCH)"
  else
    unpack_release
    ok "Code updated from the release package (branch $BRANCH)"
  fi
else
  bold "Downloading to $DIR"
  if [ -n "$GIT" ]; then
    "$GIT" clone -q --depth 1 --branch "$BRANCH" "$REPO" "$DIR"
    ok "Cloned $REPO"
  else
    unpack_release
    ok "Downloaded $TARBALL"
  fi
fi
cd "$DIR"

# composer's post-install step (package:discover) already needs bootstrap/cache.
mkdir -p storage/app/private storage/app/public storage/logs \
  storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache 2>/dev/null || true
ok "Writable folders ready"

bold "Installing PHP dependencies"
"$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --quiet
ok "Dependencies installed"

# ---- 3. setup --------------------------------------------------------------
echo
if [ "${NO_SETUP:-0}" = "1" ]; then
  bold "Skipping setup (--no-setup). Later: cd $DIR && $PHP artisan openmeru:install"
  exit 0
fi
if [ -f .env ] && grep -q '^APP_KEY=base64:' .env; then
  # An existing install: migrate and refresh caches, then offer the wizard.
  "$PHP" artisan migrate --force --no-interaction
  "$PHP" artisan optimize:clear -q
  "$PHP" artisan config:cache -q && "$PHP" artisan route:cache -q && "$PHP" artisan view:cache -q
  ok "Database migrated, caches refreshed"
  echo
  bold "Update done. To change settings or add an admin: $PHP artisan openmeru:install"
  exit 0
fi

bold "Setup"
exec "$PHP" artisan openmeru:install "${SETUP_ARGS[@]}"
