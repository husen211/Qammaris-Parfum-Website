"""Builds the Qammaris Perfumes public PWA icons (PWA-01) from the brand logo.

Black circled mark on the site's warm cream, so the home-screen icon differs from the Admin PWA
(white mark on black). Run from the repository root: python tools/build_public_pwa_icons.py
"""
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'public' / 'images' / 'logo-black2.png'
OUT = ROOT / 'public' / 'images' / 'pwa'
CREAM = (249, 249, 247)  # --color-brand-cream

# (file, size, logo share of the square). Maskable keeps the circle inside the 80% safe zone.
ICONS = [
    ('public-icon-192.png', 192, 0.86),
    ('public-icon-512.png', 512, 0.86),
    ('public-icon-maskable-512.png', 512, 0.72),
    ('public-apple-touch-icon.png', 180, 0.80),
]


def build(logo: Image.Image, size: int, share: float) -> Image.Image:
    canvas = Image.new('RGB', (size, size), CREAM)
    side = round(size * share)
    mark = logo.resize((side, side), Image.LANCZOS)
    offset = (size - side) // 2
    canvas.paste(mark, (offset, offset), mark)
    return canvas


def main() -> None:
    logo = Image.open(SOURCE).convert('RGBA')
    logo = logo.crop(logo.getchannel('A').getbbox())
    OUT.mkdir(parents=True, exist_ok=True)
    for name, size, share in ICONS:
        build(logo, size, share).save(OUT / name, optimize=True)
        print(OUT / name)


if __name__ == '__main__':
    main()
