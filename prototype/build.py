#!/usr/bin/env python3
"""Збирає прототип в один файл index.html: логотип і SVG-іконки вбудовуються в сторінку.

Запуск: python3 prototype/build.py <шлях до package/icons із @tabler/icons> <логотип.jpg>
"""
import base64
import re
import sys
from pathlib import Path

ROOT = Path(__file__).parent
OUTLINE = """home discount shopping-cart truck-delivery message-circle heart bell user-circle chevron-down
search arrow-left x phone brand-telegram brand-instagram brand-facebook brand-tiktok receipt
info-circle headset credit-card map-pin shield-lock file-text gift brand-google brand-apple flame droplet minus plus trash""".split()


def symbol(path: Path, symbol_id: str, filled: bool) -> str:
    inner = re.search(r"<svg[^>]*>(.*)</svg>", path.read_text(encoding="utf8"), re.S).group(1)
    inner = re.sub(r'<path stroke="none" d="M0 0h24v24H0z" fill="none"\s*/>', "", inner).strip()
    paint = (
        'fill="currentColor"'
        if filled
        else 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
    )
    return f'<symbol id="i-{symbol_id}" viewBox="0 0 24 24" {paint}>{inner}</symbol>'


def main() -> None:
    icons, logo = Path(sys.argv[1]), Path(sys.argv[2])
    symbols = [symbol(icons / "outline" / f"{name}.svg", name, False) for name in OUTLINE]
    symbols.append(symbol(icons / "filled" / "heart.svg", "heart-filled", True))
    sprite = '<svg xmlns="http://www.w3.org/2000/svg" hidden>' + "".join(symbols) + "</svg>"
    data = "data:image/jpeg;base64," + base64.b64encode(logo.read_bytes()).decode()
    html = (ROOT / "src" / "app.html").read_text(encoding="utf8")
    html = html.replace("{{SPRITE}}", sprite).replace("{{LOGO}}", data)
    (ROOT / "index.html").write_text(html, encoding="utf8")
    print(f"index.html: {len(html) // 1024} KB")


if __name__ == "__main__":
    main()
