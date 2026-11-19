#!/bin/sh
# Crea gli zip installabili dei due plugin (non vanno nel repository).
#   ./build.sh [cartella]     predefinita: dist/
set -eu
cd "$(dirname "$0")"
OUT="${1:-dist}"
mkdir -p "$OUT"
OUT="$(cd "$OUT" && pwd)"
for p in viewlapse-webcam viewlapse-portale-clienti; do
	v=$(sed -n 's/^ \* Version: *//p' "$p/$p.php" | head -n 1 | tr -d '\r ')
	f="$OUT/$p-$v.zip"
	rm -f "$f"
	if command -v zip >/dev/null 2>&1; then
		zip -qr -X "$f" "$p" -x '*.DS_Store' -x '*/.git*'
	else
		php -r '
			[$_, $src, $dst] = $argv;
			$z = new ZipArchive();
			$z->open($dst, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true || exit(1);
			$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
			foreach ($it as $file) { $z->addFile($file->getPathname(), str_replace("\\", "/", $file->getPathname())); }
			$z->close();' "$p" "$f"
	fi
	echo "$f"
done
