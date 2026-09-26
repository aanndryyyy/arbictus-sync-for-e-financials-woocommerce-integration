#!/usr/bin/env bash
#
# Builds the distributable plugin zip for WordPress.org.
#
# Produces dist/<slug>/ (the exact tree that goes into SVN trunk/) and
# dist/<slug>.zip (the file you upload to wordpress.org/plugins/developers/add/).
#
# Usage: bin/build-zip.sh

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="arbictus-sync-for-e-arveldaja"
DIST="${ROOT}/dist"
STAGE="${DIST}/${SLUG}"

cd "${ROOT}"

VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "${SLUG}.php" | head -1)"
STABLE_TAG="$(sed -n 's/^Stable tag:[[:space:]]*//p' readme.txt | head -1)"

if [ -z "${VERSION}" ]; then
	echo "error: could not read Version from ${SLUG}.php" >&2
	exit 1
fi

if [ "${VERSION}" != "${STABLE_TAG}" ]; then
	echo "error: plugin header Version (${VERSION}) does not match readme.txt Stable tag (${STABLE_TAG})." >&2
	echo "       WordPress.org serves whatever Stable tag points at; these must agree." >&2
	exit 1
fi

echo "==> Building ${SLUG} ${VERSION}"

rm -rf "${DIST}"
mkdir -p "${STAGE}"

echo "==> composer install --no-dev --optimize-autoloader"
composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --quiet

echo "==> Copying tree"
# --delete-excluded is unnecessary on a fresh stage dir, but keeps reruns honest.
rsync -a --exclude-from="${ROOT}/.distignore" "${ROOT}/" "${STAGE}/"

# Belt and braces: nothing that could carry credentials or dev noise.
find "${STAGE}" \
	\( -name '.DS_Store' -o -name '*.log' -o -name '.env' -o -name '.git*' \) \
	-delete

# .distignore is a denylist, so a new local folder (e.g. an editor or agent
# config dir) slips through silently. Fail loudly on anything not expected at
# the plugin root instead.
ALLOWED_TOP_LEVEL=" ${SLUG}.php composer.json LICENSE readme.txt src vendor "
for entry in "${STAGE}"/* "${STAGE}"/.[!.]*; do
	[ -e "${entry}" ] || continue
	name="$(basename "${entry}")"
	case "${ALLOWED_TOP_LEVEL}" in
		*" ${name} "*) ;;
		*)
			echo "error: unexpected '${name}' in the plugin root; add it to .distignore or ALLOWED_TOP_LEVEL." >&2
			exit 1
			;;
	esac
done

echo "==> Pruning vendor"
# WordPress.org expects a production zip "without development tools". Composer's
# --no-dev drops dev packages but leaves behind the bin shims from a previous dev
# install plus each package's own test suite and docs. LICENSE/COPYING files stay
# — the MIT and GPL notices must ship with the code they cover.
rm -rf "${STAGE}/vendor/bin"

find "${STAGE}/vendor" -mindepth 2 -maxdepth 4 -type d \
	\( -name 'tests' -o -name 'Tests' -o -name 'test' -o -name 'docs' -o -name 'doc' \
	   -o -name 'examples' -o -name '.github' -o -name 'patches' \) \
	-exec rm -rf {} +

find "${STAGE}/vendor" -mindepth 2 -maxdepth 4 -type f \
	\( -name 'README.md' -o -name 'CHANGELOG.md' -o -name 'UPGRADING.md' \
	   -o -name 'UPGRADE.md' -o -name 'CONTRIBUTING.md' -o -name 'SECURITY.md' \
	   -o -name '*.neon.dist' -o -name '*.xml.dist' -o -name 'phpunit.xml*' \
	   -o -name 'Makefile' \) \
	-delete

# The e-Financials client is dual-licensed: GPL-2.0-or-later, or a commercial
# license from the same copyright holder. This distribution exercises the GPL
# branch, and vendor/.../LICENSE carries those terms. The commercial offer is not
# part of what ships here, and leaving an "All rights reserved" file inside a GPL
# plugin only reads as a license conflict.
rm -f "${STAGE}/vendor/aanndryyyy/e-financials-php-client/LICENSE-COMMERCIAL.md"

echo "==> Zipping"
( cd "${DIST}" && zip -qr "${SLUG}.zip" "${SLUG}" )

SIZE_BYTES="$(wc -c <"${DIST}/${SLUG}.zip" | tr -d ' ')"
SIZE_MB="$(( SIZE_BYTES / 1024 / 1024 ))"

echo
echo "    dist/${SLUG}.zip  (${SIZE_MB} MB / $(( SIZE_BYTES / 1024 )) KB)"

if [ "${SIZE_BYTES}" -gt 10485760 ]; then
	echo "error: zip exceeds the 10 MB WordPress.org submission limit." >&2
	exit 1
fi

if [ "${SKIP_DEV_RESTORE:-}" = "1" ]; then
	echo
	echo "Done (dev dependencies left uninstalled: SKIP_DEV_RESTORE=1)."
	exit 0
fi

echo
echo "Restoring dev dependencies (composer install)…"
composer install --no-interaction --quiet

echo "Done."
