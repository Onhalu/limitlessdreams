#!/usr/bin/env python3
"""Generate static card pages from data/karty.json."""

from __future__ import annotations

import html
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parent
DATA_PATH = ROOT / "data" / "karty.json"


def escape(value: object) -> str:
    return html.escape(str(value), quote=True)


def render_card(card: dict) -> str:
    card_id = int(card["id"])
    name = escape(card["nazev"])
    kind = escape(card.get("typ", ""))
    height = escape(card.get("vyska", ""))
    fact = escape(card.get("zajimavost", ""))

    has_image = bool(card.get("has_image"))
    if has_image:
        image_class = "card-image"
        visual = f'<img src="../img/{card_id}.webp" alt="{name}">'
    else:
        image_class = "card-image card-image--placeholder"
        visual = f'<span>{name}</span>'

    wiki_url = card.get("wiki_url")
    wiki_link = ""
    if wiki_url:
        wiki_link = (
            f'\n      <a class="card-link" href="{escape(wiki_url)}" '
            'rel="noopener" target="_blank">Více na Wikipedii</a>'
        )

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
      <div class="{image_class}"{f' role="img" aria-label="{name}"' if not has_image else ""}>{visual}</div>
      <h1 class="card-title">{name}</h1>
      <p class="card-meta">{kind} · {height} m n. m.</p>
      <p class="card-body">{fact}</p>{wiki_link}
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
        raise ValueError(f"Expected 100 cards, got {len(cards) if isinstance(cards, list) else 'non-list'}")

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
