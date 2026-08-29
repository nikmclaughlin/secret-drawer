#!/usr/bin/env bash
#
# Secret Drawer — release build.
#
# Produces a WordPress-installable zip in build/, excluding dev-only files.
# The export directory is also exactly what gets committed to wordpress.org
# SVN trunk; .wordpress.org/ (screenshots, banner) is what goes in the SVN
# assets directory. Same artifact, two destinations.
#
# Usage:
#   ./bin/build.sh              # build build/secret-drawer-{version}.zip
#   ./bin/build.sh --release    # also tag, push, and cut a GitHub release
#
set -euo pipefail

cd "$(dirname "$0")/.."
ROOT="$PWD"
SLUG="secret-drawer"
BUILD_DIR="$ROOT/build"
EXPORT="$BUILD_DIR/$SLUG"

# --- Version sanity -------------------------------------------------------
VERSION=$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' secret-drawer.php | head -1)
STABLE=$(sed -n 's/^Stable tag:[[:space:]]*//p' readme.txt | head -1)
[ -n "$VERSION" ] || { echo "✗ Could not read Version from secret-drawer.php" >&2; exit 1; }
if [ -n "$STABLE" ] && [ "$STABLE" != "$VERSION" ]; then
	echo "⚠ readme.txt Stable tag ($STABLE) ≠ plugin version ($VERSION) — fix before releasing." >&2
	[ "${1:-}" != "--release" ] || exit 1
fi

ZIP="$BUILD_DIR/$SLUG-$VERSION.zip"

# --- Exports --------------------------------------------------------------
# Everything repo-only that must never ship in the zip. Keep in sync with
# .gitignore where applicable.
EXCLUDES=(
	.git .wordpress.org bin build
	PLAN.md AGENTS.md SECRET-DRAWER-EXTENDING.md README.md .gitignore
	.DS_Store '*.log'
	# Original 1944px source photo (1.7MB); only the web copy ships.
	'Socrates Louvre.jpg'
)

echo "▸ Cleaning $BUILD_DIR"
rm -rf "$BUILD_DIR"
mkdir -p "$EXPORT"

ARGS=()
for e in "${EXCLUDES[@]}"; do ARGS+=( --exclude="$e" ); done
rsync -a -q "${ARGS[@]}" "$ROOT/" "$EXPORT/"

# Sanity: nothing oversized should ever ship.
find "$EXPORT" -type f -size +1M -print | while read -r f; do
	echo "⚠ File over 1MB shipping in zip: ${f#"$EXPORT"/}" >&2
done

echo "▸ Zipping → $ZIP"
( cd "$BUILD_DIR" && zip -rq "$(basename "$ZIP")" "$SLUG" )

echo "✓ Built $(basename "$ZIP") ($(du -h "$ZIP" | cut -f1 | tr -d '\t'))"

# --- Optional: publish ----------------------------------------------------
if [ "${1:-}" = "--release" ]; then
	TAG="$VERSION"
	echo "▸ Tagging $TAG"
	git tag -f -a "$TAG" -m "Secret Drawer $TAG"

	echo "▸ Pushing main + tag"
	GIT_TERMINAL_PROMPT=0 git -c credential.helper= \
		-c "credential.helper=!/opt/homebrew/bin/gh auth git-credential" \
		push -f origin main "$TAG"

	echo "▸ Creating GitHub release $TAG"
	gh release create "$TAG" "$ZIP" \
		--title "Secret Drawer $TAG" \
		--generate-notes \
		|| gh release upload "$TAG" "$ZIP" --clobber

	echo "✓ Released $TAG — see https://github.com/$(git remote get-url origin | sed 's#.*github.com/##; s#\.git$##')/releases/tag/$TAG"
fi