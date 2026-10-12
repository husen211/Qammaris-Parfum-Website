"""Builds the homepage "Distributor Resmi" brand marks (UX-02) from the Owner-supplied logo files.

Every logo becomes one dark mark (#1A1A1A) on a transparent background: the logo's own background (white, black,
navy, or transparency) is removed by colour distance, the result is trimmed, and sized by visual area so wide
wordmarks and square emblems look balanced. Output: public/images/brands/<brand-slug>.webp

Usage: python tools/build_brand_logos.py "<folder with the supplied logos>"
"""
import sys
from pathlib import Path

from PIL import Image, ImageChops, ImageFilter

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'public' / 'images' / 'brands'
INK = (26, 26, 26)  # --color-brand-black
AREA = 230 * 92     # target visual area at 2x display density
MAX_W, MAX_H = 380, 132

# Supplied file -> production brand slug.
SOURCES = {
    'afnan logo.webp': 'afnan',
    'ahmed logo.png': 'ahmed-al-maghribi',
    'armaf logo.jpeg': 'armaf',
    'emper logo.webp': 'emper',
    'frag world logo.webp': 'frag-world',
    'khadlaj logo.webp': 'khadlaj',
    'mykonos logo.jpg': 'mykonos',
    'osma logo.png': 'osma',
    'project1945 logo.jpg': 'project1945',
    'rayhaan logo.webp': 'rayhaan',
    'reef logo.jpeg': 'reef-perfume',
    'riifs logo.webp': 'riffs',
    'zimya logo.jpg': 'zimaya-by-afnan',
}


def mark(path: Path) -> Image.Image:
    source = Image.open(path).convert('RGBA')
    source.thumbnail((1600, 1600))
    # Drop the outer 1% where JPEG exports often carry a stray edge line.
    inset = max(2, round(min(source.size) * 0.01))
    source = source.crop((inset, inset, source.width - inset, source.height - inset))
    flat = Image.new('RGB', source.size, (255, 255, 255))
    flat.paste(source, mask=source.getchannel('A'))
    # Background = the most common colour on the outer border.
    border = [flat.getpixel((x, y)) for x in range(flat.width) for y in (0, flat.height - 1)]
    border += [flat.getpixel((x, y)) for y in range(flat.height) for x in (0, flat.width - 1)]
    background = max(set(border), key=border.count)
    distance = ImageChops.difference(flat, Image.new('RGB', flat.size, background)).convert('L')
    histogram, total, peak = distance.histogram(), 0, 1
    for level, count in enumerate(histogram):
        total += count
        if total >= distance.width * distance.height * 0.995:
            peak = max(1, level)
            break
    alpha = distance.point(lambda d: 0 if d < 24 else min(255, int(255 * (d - 24) / max(1, peak - 24))))
    alpha = alpha.filter(ImageFilter.MedianFilter(3))
    box = alpha.point(lambda a: 255 if a > 40 else 0).getbbox()
    out = Image.new('RGBA', flat.size, INK + (0,))
    out.putalpha(alpha)
    out = out.crop(box)
    ratio = out.width / out.height
    height = min(MAX_H, (AREA / ratio) ** 0.5)
    width = min(MAX_W, height * ratio)
    height = width / ratio
    return out.resize((max(1, round(width)), max(1, round(height))), Image.LANCZOS)


def main() -> None:
    folder = Path(sys.argv[1])
    OUT.mkdir(parents=True, exist_ok=True)
    for name, slug in SOURCES.items():
        image = mark(folder / name)
        image.save(OUT / f'{slug}.webp', 'WEBP', quality=90, method=6)
        print(f'{slug}.webp {image.size}')


if __name__ == '__main__':
    main()
