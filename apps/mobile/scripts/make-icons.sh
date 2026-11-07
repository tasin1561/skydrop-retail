#!/usr/bin/env sh
# Regenerate app icons from assets/icon-src/*.svg (Midnight Luxe: ink background,
# violet-gradient parachute, coral parcel). Needs rsvg-convert (librsvg).
set -e
cd "$(dirname "$0")/../assets"
rsvg-convert -w 1024 -h 1024 icon-src/icon.svg   -o images/icon.png
rsvg-convert -w 1024 -h 1024 icon-src/fg.svg     -o images/android-icon-foreground.png
rsvg-convert -w 1024 -h 1024 icon-src/bg.svg     -o images/android-icon-background.png
rsvg-convert -w 1024 -h 1024 icon-src/mono.svg   -o images/android-icon-monochrome.png
rsvg-convert -w 1024 -h 1024 icon-src/splash.svg -o images/splash-icon.png
rsvg-convert -w 48 -h 48     icon-src/icon.svg   -o images/favicon.png
echo "icons written to assets/images"
