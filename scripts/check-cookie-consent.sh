#!/usr/bin/env bash
# Fail if any HTML page is missing the self-hosted cookie banner,
# still calls Google Fonts, or embeds YouTube/Heyzine before consent.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root"

missing=0
count=0

# vandery/ is a separate document root (vandery.limitlessdreams.cz) with no
# tracking, embeds, or forms. Root-relative /assets/ld-consent.js would 404
# there, and the live font files do not send CORS, so the page ships its own
# type and skips the banner.
while IFS= read -r -d '' file; do
  count=$((count + 1))
  if ! grep -q '/assets/ld-consent.js' "$file"; then
    echo "MISSING ld-consent.js: $file"
    missing=1
  fi
  if ! grep -q '/assets/cookieconsent.umd.js' "$file"; then
    echo "MISSING cookieconsent.umd.js: $file"
    missing=1
  fi
  if ! grep -q '/assets/cookieconsent.css' "$file"; then
    echo "MISSING cookieconsent.css: $file"
    missing=1
  fi
  if ! grep -q 'data-cc="show-preferencesModal"' "$file"; then
    echo "MISSING Nastavení cookies link: $file"
    missing=1
  fi
done < <(find . -name '*.html' -not -path './.git/*' -not -path './vandery/*' -print0)

if grep -R -n -E 'fonts\.(googleapis|gstatic)\.com' --include='*.html' --include='*.css' --include='*.js' .; then
  echo "Google Fonts host is still referenced"
  missing=1
fi

if grep -R -n -E 'youtube\.com/embed' --include='*.html' --include='*.js' .; then
  echo "YouTube embed still uses youtube.com instead of youtube-nocookie.com"
  missing=1
fi

# data-ld-src is the gated URL. A real src attribute would load the embed immediately.
if grep -R -n -E '(^|[^a-zA-Z0-9-])src="https://(www\.)?youtube' --include='*.html' .; then
  echo "YouTube iframe src is set in HTML before consent"
  missing=1
fi

if grep -R -n -E '(^|[^a-zA-Z0-9-])src="https://heyzine\.com' --include='*.html' .; then
  echo "Heyzine iframe src is set in HTML before consent"
  missing=1
fi

if ! grep -q 'youtube-nocookie.com/embed/dQw4w9WgXcQ' crash-floor/index.html; then
  echo "crash-floor YouTube id is not kept behind the nocookie placeholder"
  missing=1
fi

for form in ebook-kondice/index.html crash-floor/index.html; do
  if ! grep -q 'src="https://form.fapi.cz/script.php' "$form"; then
    echo "FAPI order form script missing: $form"
    missing=1
  fi
  if grep -q 'text/plain' "$form"; then
    echo "FAPI or another script is blocked with type=text/plain: $form"
    missing=1
  fi
done

if ! grep -q 'CookieConsent 3.1.0' assets/cookieconsent.umd.js; then
  echo "cookieconsent.umd.js is not pinned to vanilla-cookieconsent 3.1.0"
  missing=1
fi

if [[ ! -f assets/cookieconsent.css || ! -f assets/ld-consent.js ]]; then
  echo "Consent assets missing from /assets"
  missing=1
fi

if [[ ! -f assets/fonts/plus-jakarta-sans-latin.woff2 || ! -f assets/fonts/plus-jakarta-sans-latin-ext.woff2 ]]; then
  echo "Self-hosted Plus Jakarta Sans woff2 files are missing"
  missing=1
fi

if [[ "$missing" -ne 0 ]]; then
  echo "FAIL: cookie consent check ($count HTML pages scanned)"
  exit 1
fi

echo "Note: vandery/ is excluded (standalone subdomain, no tracking)."
echo "OK: $count HTML pages include cookieconsent 3.1.0, ld-consent.js, and Nastavení cookies"
