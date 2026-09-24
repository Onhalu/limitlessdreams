#!/usr/bin/env python3
"""Generate static card pages from data/karty.json (Viateria wireframe UX)."""

from __future__ import annotations

import html
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent
DATA_PATH = ROOT / "data" / "karty.json"
if not DATA_PATH.exists():
    DATA_PATH = ROOT / "karty.json"


def escape(value: object) -> str:
    return html.escape(str(value), quote=True)


def initials_from_name(name: str) -> str:
    """1–2 letter initials from nazev (first letters of first 1–2 words)."""
    words = [w for w in re.split(r"\s+", name.strip()) if w]
    letters: list[str] = []
    for word in words:
        for ch in word:
            if ch.isalpha():
                letters.append(ch.upper())
                break
        if len(letters) >= 2:
            break
    return "".join(letters[:2]) or "?"


def format_region_line(region: object, vyska: object) -> str:
    region_s = str(region or "").strip()
    if region_s and not region_s.lower().endswith("kraj"):
        region_s = f"{region_s} kraj"

    vyska_s = ""
    if vyska is not None and str(vyska).strip() != "":
        vyska_s = f"{vyska} m n. m."

    if region_s and vyska_s:
        return f"{region_s} · {vyska_s}"
    return region_s or vyska_s


def render_card(card: dict) -> str:
    card_id = int(card["id"])
    name = escape(card["nazev"])
    kind = escape(card.get("typ", ""))
    region_line = escape(format_region_line(card.get("region"), card.get("vyska")))
    fact = escape(card.get("zajimavost", ""))
    id_padded = f"{card_id:03d}"

    has_image = bool(card.get("has_image"))
    if has_image:
        circle_class = "card-circle"
        visual = f'<img src="../img/{card_id}.webp" alt="{name}">'
    else:
        circle_class = "card-circle card-circle--placeholder"
        initials = escape(initials_from_name(str(card["nazev"])))
        visual = f'<span class="card-initials" aria-hidden="true">{initials}</span>'

    wiki_url = card.get("wiki_url")
    wiki_link = ""
    if wiki_url:
        wiki_link = (
            f'\n      <a class="card-wiki" href="{escape(wiki_url)}" '
            'rel="noopener" target="_blank">Wikipedie</a>'
        )

    aria = f' role="img" aria-label="{name}"' if not has_image else ""

    return f'''<!doctype html>
<html lang="cs">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{name} · Česko spolu</title>
    <link rel="stylesheet" href="../card.css">
  </head>
  <body>
    <main class="card">
      <h1 class="card-title">{name}</h1>
      <p class="card-region">{region_line}</p>
      <div class="{circle_class}"{aria}>{visual}</div>
      <p class="card-type">{kind} · <span class="card-id">#{id_padded}</span></p>
      <div class="card-fact"><p>{fact}</p></div>{wiki_link}
    </main>
  </body>
</html>
'''


def render_index(cards: list[dict]) -> str:
    links = "\n".join(
        f'        <li><a href="{int(card["id"])}/">{escape(card["nazev"])}</a></li>'
        for card in cards
    )
    return f'''<!doctype html>
<html lang="cs">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Česko spolu</title>
    <link rel="stylesheet" href="card.css">
  </head>
  <body>
    <main class="card-list">
      <h1>Česko spolu</h1>
      <ul>
{links}
      </ul>
    </main>
  </body>
</html>
'''


def main() -> None:
    cards = json.loads(DATA_PATH.read_text(encoding="utf-8"))
    if not isinstance(cards, list) or len(cards) != 100:
        raise ValueError(
            f"Expected 100 cards, got {len(cards) if isinstance(cards, list) else 'non-list'}"
        )

    ids = [int(card["id"]) for card in cards]
    if sorted(ids) != list(range(1, 101)):
        raise ValueError("Card IDs must be exactly 1 through 100")

    for card in cards:
        card_dir = ROOT / str(int(card["id"]))
        card_dir.mkdir(exist_ok=True)
        (card_dir / "index.html").write_text(render_card(card), encoding="utf-8")

    (ROOT / "index.html").write_text(render_index(cards), encoding="utf-8")
    print(f"Generated {len(cards)} card pages and index.html")


if __name__ == "__main__":
    main()
