#!/usr/bin/env python3
"""Снимок живого WP 2026 → статический архив без WordPress."""

from __future__ import annotations

import csv
import html as html_lib
import io
import json
import re
import shutil
import ssl
import sys
import urllib.error
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from html.parser import HTMLParser
from pathlib import Path

REPO = Path(__file__).resolve().parents[3]
ROOT = Path(__file__).resolve().parents[1]
SITE = ROOT / "site"
ORIGIN_PUNY = "https://xn--80aap0aec3aidne.xn--p1ai"
ORIGIN_IDN = "https://хануманфест.рф"
ARCHIVE_HOST = "https://2026.хануманфест.рф"
LIVE_2027 = "https://хануманфест.рф"
UA = "HanumanFestArchive/2026"
SSL_CTX = ssl.create_default_context()
SHEET_ID = "1i7rRY7WpoY-3TUyeInP2vRYXiEkIHOOV16hIigPrNFI"
SHEET_GID = "319789815"
SKIP_ASSET_RE = re.compile(
    r"forminator|payment\.js|jivo|wordpress-seo|wp-emoji|wp-embed"
    r"|comment-reply|admin-bar|dashicons|wp-json|xmlrpc",
    re.I,
)
ASSET_EXT = {
    ".css", ".js", ".jpg", ".jpeg", ".png", ".webp", ".gif", ".svg",
    ".ico", ".woff", ".woff2", ".ttf", ".otf", ".mp4", ".webm", ".map",
}
SCRIPT_KILL = (
    "forminator", "payment-js", "wp-emoji", "wp-embed", "jivo",
    "ykdata", "admin-ajax", "jquery-core-js", "jquery-migrate",
)

REGISTER_HTML = """<section class="register d-flex flex-column justify-content-center align-items-center" id="register">
    <div class="container text-center py-4">
        <h2 class="text-brand-main text-center mb-4">Регистрация 2026 закрыта</h2>
        <p class="mb-4 fs-5">Сайт сохранён как архив прошедшего фестиваля. Регистрация на следующий сезон — на основном сайте.</p>
        <a href="https://хануманфест.рф/" class="btn btn-lg btn-brand-main text-white fw-bold px-4 py-3">Сайт Хануман Фест 2027</a>
    </div>
</section>"""

EVENT_JSONLD = {
    "@context": "https://schema.org",
    "@type": "Event",
    "name": "Хануман Фест 2026",
    "description": "Архив сайта прошедшего фестиваля Хануман Фест: 26–28 июня 2026, эко-поселение Большая Медведица.",
    "eventStatus": "https://schema.org/EventCompleted",
    "eventAttendanceMode": "https://schema.org/OfflineEventAttendanceMode",
    "startDate": "2026-06-26",
    "endDate": "2026-06-28",
    "image": f"{ARCHIVE_HOST}/wp-content/uploads/2025/10/logo-hanuman.png",
    "url": f"{ARCHIVE_HOST}/",
    "location": {
        "@type": "Place",
        "name": "Эко-поселение Большая Медведица",
        "address": {
            "@type": "PostalAddress",
            "addressRegion": "Челябинская область",
            "addressCountry": "RU",
        },
    },
    "organizer": {"@type": "Organization", "name": "Хануман Фест", "url": LIVE_2027 + "/"},
}


def idna_url(url: str) -> str:
    parts = urllib.parse.urlsplit(url)
    if not parts.hostname:
        return url
    host = parts.hostname.encode("idna").decode("ascii")
    auth = ""
    if parts.username:
        auth = parts.username
        if parts.password:
            auth += ":" + parts.password
        auth += "@"
    netloc = f"{auth}{host}"
    if parts.port:
        netloc += f":{parts.port}"
    path = urllib.parse.quote(urllib.parse.unquote(path_unquote_safe(parts.path)), safe="/%@:_-.,+()")
    return urllib.parse.urlunsplit((parts.scheme, netloc, path, parts.query, parts.fragment))


def path_unquote_safe(path: str) -> str:
    try:
        return urllib.parse.unquote(path)
    except Exception:
        return path


def fetch(url: str, timeout: int = 45) -> tuple[bytes, str, int]:
    req = urllib.request.Request(idna_url(url), headers={"User-Agent": UA, "Accept": "*/*"})
    with urllib.request.urlopen(req, context=SSL_CTX, timeout=timeout) as resp:
        return resp.read(), resp.headers.get("Content-Type", ""), resp.status


def is_origin_url(url: str) -> bool:
    low = url.split("?", 1)[0]
    return (
        low.startswith(ORIGIN_PUNY)
        or low.startswith(ORIGIN_IDN)
        or low.startswith("//xn--80aap0aec3aidne.xn--p1ai")
        or low.startswith("//хануманфест.рф")
    )


def origin_path(url: str) -> str:
    if url.startswith("//"):
        url = "https:" + url
    parts = urllib.parse.urlsplit(url)
    return path_unquote_safe(parts.path) or "/"


def local_page_path(url_path: str) -> Path:
    path = path_unquote_safe(url_path).split("?")[0]
    if not path.startswith("/"):
        path = "/" + path
    if path == "/":
        return SITE / "index.html"
    if path.endswith("/"):
        return SITE / path.strip("/") / "index.html"
    suffix = Path(path).suffix.lower()
    if suffix in {".html", ".xml", ".txt", ".json", ".css", ".js", ".csv"}:
        return SITE / path.lstrip("/")
    return SITE / path.strip("/") / "index.html"


def canonical_for(url_path: str) -> str:
    path = path_unquote_safe(url_path).split("?")[0]
    if path in {"", "/"}:
        return ARCHIVE_HOST + "/"
    if not path.endswith("/"):
        path += "/"
    return ARCHIVE_HOST + path


class AssetCollector(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.urls: set[str] = set()

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        ad = {k: v for k, v in attrs if v}
        for key in ("src", "href", "data-src", "poster"):
            if key in ad:
                self.urls.add(ad[key])
        if "srcset" in ad:
            for part in ad["srcset"].split(","):
                u = part.strip().split(" ")[0]
                if u:
                    self.urls.add(u)
        style = ad.get("style")
        if style:
            self.urls.update(re.findall(r"url\(\s*['\"]?([^'\")]+)['\"]?\s*\)", style, flags=re.I))


def collect_css_urls(css: str, base: str) -> set[str]:
    found: set[str] = set()
    for match in re.finditer(r"url\(\s*['\"]?([^'\")]+)['\"]?\s*\)", css, flags=re.I):
        raw = match.group(1).strip()
        if raw.startswith("data:"):
            continue
        found.add(urllib.parse.urljoin(base, raw))
    return found


def should_skip_asset(url: str) -> bool:
    return bool(SKIP_ASSET_RE.search(url))


def save_bytes(dest: Path, data: bytes) -> None:
    dest.parent.mkdir(parents=True, exist_ok=True)
    dest.write_bytes(data)


def copy_local_fallback(url_path: str) -> Path | None:
    path = origin_path(ORIGIN_PUNY + url_path) if url_path.startswith("/") else url_path
    path = path.split("?")[0]
    uploads = re.match(r"^/wp-content/uploads/(\d{4}/\d{2}/.+)$", path)
    if uploads:
        local = REPO / "public/uploads/wp" / uploads.group(1)
        if local.is_file():
            return local
    theme = re.match(r"^/wp-content/themes/hanumanfest/(.+)$", path)
    if theme:
        local = REPO / "legacy/wordpress-theme/hanumanfest" / theme.group(1)
        if local.is_file():
            return local
    return None


def download_asset(url: str) -> tuple[str, Path | None, str]:
    if url.startswith("//"):
        url = "https:" + url
    if should_skip_asset(url):
        return url, None, "skip"
    if not is_origin_url(url):
        return url, None, "external"
    path = origin_path(url)
    queryless = path.split("?")[0]
    if Path(queryless).suffix.lower() not in ASSET_EXT:
        return url, None, "not-asset"
    dest = SITE / queryless.lstrip("/")
    if dest.is_file() and dest.stat().st_size > 0:
        return url, dest, "exists"
    local = copy_local_fallback(queryless)
    if local is not None:
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(local, dest)
        return url, dest, "local"
    try:
        data, _ctype, _status = fetch(url)
        save_bytes(dest, data)
        return url, dest, "download"
    except Exception as exc:
        return url, None, f"err:{exc}"


def sitemap_locs(xml: str) -> list[str]:
    return re.findall(r"<loc>([^<]+)</loc>", xml)


def keep_archive_path(url_or_path: str) -> bool:
    raw = url_or_path
    if raw.startswith("http") or raw.startswith("//"):
        raw = origin_path(raw)
    path = path_unquote_safe(raw).split("?")[0].rstrip("/") or "/"
    if path == "/":
        return True
    if path == "/schedule":
        return True
    name = path.lstrip("/")
    return (
        name.startswith("питание")
        or name.startswith("политика-")
        or "оферта" in name
    )


def gather_page_urls() -> list[str]:
    urls: list[str] = [ORIGIN_PUNY + "/"]
    index_xml, _, _ = fetch(ORIGIN_PUNY + "/sitemap_index.xml")
    for sm in sitemap_locs(index_xml.decode("utf-8", "replace")):
        if "page-sitemap" not in sm:
            continue
        body, _, _ = fetch(sm)
        urls.extend(sitemap_locs(body.decode("utf-8", "replace")))
    seen: set[str] = set()
    out: list[str] = []
    for u in urls:
        if u in seen or not keep_archive_path(u):
            continue
        seen.add(u)
        out.append(u)
    return out


def strip_tags_block(html: str, pattern: str) -> str:
    return re.sub(pattern, "", html, flags=re.I | re.S)


def replace_register_section(html: str) -> str:
    new, n = re.subn(
        r'<section\s+class="register\b.*?</section>',
        REGISTER_HTML,
        html,
        count=1,
        flags=re.I | re.S,
    )
    if n:
        return new
    # fallback: forminator form only
    new, n = re.subn(
        r'<form\b[^>]*id="forminator-module-156".*?</form>',
        REGISTER_HTML,
        html,
        count=1,
        flags=re.I | re.S,
    )
    return new if n else html


def rewrite_internal_urls(html: str) -> str:
    html = html.replace(ORIGIN_PUNY, "")
    html = html.replace("http://xn--80aap0aec3aidne.xn--p1ai", "")
    html = html.replace("//xn--80aap0aec3aidne.xn--p1ai", "")
    # percent-encoded paths → readable unicode
    def decode_path(match: re.Match[str]) -> str:
        return urllib.parse.unquote(match.group(0))

    html = re.sub(r"(?:href|src|content)=([\"'])/[^\"']+%[0-9A-Fa-f]{2}[^\"']*\1", lambda m: urllib.parse.unquote(m.group(0)), html)
    html = re.sub(r"url\((['\"]?)/[^)'\"]+%[0-9A-Fa-f]{2}[^)'\"]*\1?\)", lambda m: urllib.parse.unquote(m.group(0)), html)
    # same-site anchors that currently point at apex
    html = re.sub(
        r'https://хануманфест\.рф#(program|guests|masters|contacts|about|cost|infoblocks|late|hero)\b',
        r"/#\1",
        html,
    )
    html = html.replace("https://хануманфест.рф#register", f"{LIVE_2027}/")
    html = re.sub(r'href="#register"', f'href="{LIVE_2027}/"', html)
    html = re.sub(r"href='/#register'", f'href="{LIVE_2027}/"', html)
    html = html.replace('href="/#register"', f'href="{LIVE_2027}/"')
    html = html.replace("https://clck.su/peQdI", "/schedule/")
    # drop query ver= on local assets
    html = re.sub(r"(/wp-content/[^\"'?#\s]+)\?ver=[^\"'\s>]+", r"\1", html)
    html = re.sub(r"(/wp-includes/[^\"'?#\s]+)\?ver=[^\"'\s>]+", r"\1", html)
    html = re.sub(
        r"https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.3/dist/css/bootstrap\.min\.css[^\"']*",
        "/assets/vendor/bootstrap.min.css",
        html,
    )
    html = re.sub(
        r"https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.3/dist/js/bootstrap\.bundle\.min\.js[^\"']*",
        "/assets/vendor/bootstrap.bundle.min.js",
        html,
    )
    html = re.sub(
        r"https://fonts\.googleapis\.com/css2\?family=Montserrat:[^\"']+",
        "/assets/vendor/fonts-montserrat.css",
        html,
    )
    return html


def page_kind(url_path: str) -> str:
    p = path_unquote_safe(url_path).rstrip("/") or "/"
    if p == "/":
        return "home"
    if "питание" in p:
        return "kitchen"
    if "политик" in p or "оферта" in p:
        return "legal"
    if p.startswith("/program"):
        return "program"
    if p.startswith("/schedule"):
        return "schedule"
    return "inner"


def seo_title(kind: str, current: str) -> str:
    current = re.sub(r"\s+", " ", current).strip()
    if kind == "home":
        return "Хануман Фест 2026 — как это было"
    if "архив" in current.lower() or "2026" in current:
        if "архив" not in current.lower():
            return current.replace(" - Хануман Фест", " — архив").rstrip(" -") + " — архив"
        return current
    base = re.sub(r"\s*[-|–]\s*Хануман Фест\s*$", "", current)
    return f"{base} — архив Хануман Фест 2026"


def seo_description(kind: str, title: str) -> str:
    if kind == "home":
        return (
            "Архив сайта Хануман Фест 2026: программа, гости, мастера и атмосфера фестиваля "
            "26–28 июня в эко-поселении Большая Медведица. Регистрация закрыта."
        )
    if kind == "kitchen":
        return "Как было организовано питание на Хануман Фест 2026. Архив прошедшего фестиваля."
    if kind == "schedule":
        return "Расписание Хануман Фест 2026 — архив программы прошедшего фестиваля."
    if kind == "program":
        return f"{title}. Архив программы Хануман Фест 2026."
    return f"{title}. Архив сайта Хануман Фест 2026 — как это было."


def inject_seo(html: str, url_path: str) -> str:
    kind = page_kind(url_path)
    m = re.search(r"<title[^>]*>(.*?)</title>", html, flags=re.I | re.S)
    current = re.sub(r"<[^>]+>", "", m.group(1)).strip() if m else "Хануман Фест 2026"
    title = seo_title(kind, current)
    desc = seo_description(kind, title)
    canon = canonical_for(url_path)
    html = re.sub(r"<title[^>]*>.*?</title>", f"<title>{title}</title>", html, count=1, flags=re.I | re.S)
    html = strip_tags_block(html, r'<link[^>]+rel=["\']canonical["\'][^>]*>')
    html = strip_tags_block(html, r'<meta[^>]+name=["\']description["\'][^>]*>')
    html = strip_tags_block(html, r'<meta[^>]+property=["\']og:title["\'][^>]*>')
    html = strip_tags_block(html, r'<meta[^>]+property=["\']og:description["\'][^>]*>')
    html = strip_tags_block(html, r'<meta[^>]+property=["\']og:url["\'][^>]*>')
    html = strip_tags_block(html, r'<meta[^>]+name=["\']robots["\'][^>]*>')
    html = strip_tags_block(html, r'<script type="application/ld\+json" class="yoast-schema-graph">.*?</script>')
    html = strip_tags_block(html, r'<meta name="generator"[^>]*>')
    ld = json.dumps(EVENT_JSONLD, ensure_ascii=False)
    extra = (
        f'<meta name="description" content="{desc}">\n'
        f'<link rel="canonical" href="{canon}">\n'
        f'<meta name="robots" content="index,follow">\n'
        f'<meta property="og:locale" content="ru_RU">\n'
        f'<meta property="og:type" content="website">\n'
        f'<meta property="og:title" content="{title}">\n'
        f'<meta property="og:description" content="{desc}">\n'
        f'<meta property="og:url" content="{canon}">\n'
        f'<meta property="og:site_name" content="Хануман Фест 2026">\n'
        f'<script type="application/ld+json">{ld}</script>\n'
    )
    if re.search(r"<meta charset=", html, flags=re.I):
        html = re.sub(r"(<meta charset=[^>]*>)", r"\1\n" + extra, html, count=1, flags=re.I)
    else:
        html = html.replace("<head>", "<head>\n" + extra, 1)
    html = re.sub(r'<html\b[^>]*>', '<html lang="ru">', html, count=1, flags=re.I)
    return html


def strip_runtime_junk(html: str) -> str:
    def drop_script(match: re.Match[str]) -> str:
        blob = match.group(0).lower()
        return "" if any(token in blob for token in SCRIPT_KILL) else match.group(0)

    html = re.sub(r"<script\b[^>]*>.*?</script>", drop_script, html, flags=re.I | re.S)
    html = strip_tags_block(html, r"<link[^>]+(?:forminator|wordpress-seo|oembed)[^>]*>")
    html = strip_tags_block(html, r"<style[^>]*id=['\"]forminator[^>]*>.*?</style>")
    html = strip_tags_block(html, r"<style[^>]*id=['\"]wp-emoji-styles-inline-css['\"][^>]*>.*?</style>")
    html = strip_tags_block(html, r"<script type=\"speculationrules\">.*?</script>")
    html = strip_tags_block(html, r"<!--\s*(This site is optimized with the )?Yoast SEO[\s\S]*?-->")
    html = strip_tags_block(html, r'<div class="forminator-ui[^"]*"[\s\S]*?</form>\s*</div>')
    html = re.sub(r'<link rel=["\']https://api\.w\.org/["\'][^>]*>', "", html, flags=re.I)
    html = re.sub(r'<link rel=["\']alternate["\'][^>]+application/json[^>]*>', "", html, flags=re.I)
    html = re.sub(r'<link rel=["\']EditURI["\'][^>]*>', "", html, flags=re.I)
    html = re.sub(r'<link rel=["\']shortlink["\'][^>]*>', "", html, flags=re.I)
    html = re.sub(r'<link rel=["\']https://oembed\.[^>]*>', "", html, flags=re.I)
    html = re.sub(r'<link rel=["\']dns-prefetch["\'][^>]*>', "", html, flags=re.I)
    html = re.sub(
        r'<link rel=["\']preconnect["\'] href=["\']https://fonts\.(?:googleapis|gstatic)\.com[^>]*>',
        "",
        html,
        flags=re.I,
    )
    html = html.replace("xn--80aap0aec3aidne.xn--p1ai", "2026.хануманфест.рф")
    return html


def sanitize_html(html: str, url_path: str) -> str:
    html = replace_register_section(html)
    html = strip_runtime_junk(html)
    html = rewrite_internal_urls(html)
    html = inject_seo(html, url_path)
    html = html.replace("© 2027 ", "© 2026 ")
    return html


HIDDEN_TITLE_RE = re.compile(r"чек|занято|подготовка", re.I)
DAY_SHORT = {"Пятница": "Пт", "Суббота": "Сб", "Воскресенье": "Вс"}
MEAL_TITLES = {"завтрак", "обед", "ужин"}


def clean_venue_name(name: str) -> str:
    name = re.sub(r"\s+", " ", name).strip()
    if "Стеклянный" in name and "Детский" in name:
        return "Стеклянный шатёр / Детский городок"
    return name


def venue_slug(name: str) -> str:
    slug = re.sub(r"[^a-zа-яё0-9]+", "-", name.lower(), flags=re.I)
    return slug.strip("-") or "venue"


def clean_title(title: str) -> str:
    return re.sub(r"\s+", " ", title).strip()


def event_modifier(title: str) -> str:
    low = title.lower()
    if low in MEAL_TITLES:
        return "hf-schedule__event--meal"
    if "начало" in low or "завершение" in low or "заезд" in low or "отъезд" in low:
        return "hf-schedule__event--service"
    return ""


def parse_schedule_days(csv_text: str) -> tuple[list[str], list[dict]]:
    reader = csv.reader(io.StringIO(csv_text))
    rows = list(reader)
    if not rows:
        return [], []
    venues = [clean_venue_name(c) for c in rows[0][3:] if c.strip()]
    days: list[dict] = []
    current: dict | None = None
    current_hour = ""

    def ensure_day(iso: str, label: str, short: str, display: str) -> dict:
        nonlocal current
        if current is None or current["iso"] != iso:
            current = {
                "iso": iso,
                "label": label,
                "short": short,
                "display": display,
                "events": [],
            }
            days.append(current)
        return current

    for row in rows[1:]:
        if not row:
            continue
        a = row[0].strip() if len(row) > 0 else ""
        b = row[1].strip() if len(row) > 1 else ""
        c = row[2].strip() if len(row) > 2 else ""
        if a in DAY_SHORT:
            continue
        if a == "дата":
            continue
        date_match = re.match(r"(\d{2})\.(\d{2})\.(\d{2})", a)
        if date_match:
            day, month, year = date_match.groups()
            iso = f"20{year}-{month}-{day}"
            weekday = {("26", "06"): ("Пятница", "Пт"), ("27", "06"): ("Суббота", "Сб"), ("28", "06"): ("Воскресенье", "Вс")}.get(
                (day, month), ("", "")
            )
            ensure_day(iso, weekday[0], weekday[1], f"{day}.{month}")
        if b.isdigit():
            current_hour = b.zfill(2)
        minute = c.zfill(2) if c.isdigit() else "00"
        if not current_hour or current is None:
            continue
        for index, venue in enumerate(venues):
            raw = row[3 + index].strip() if len(row) > 3 + index else ""
            title = clean_title(raw)
            if not title or HIDDEN_TITLE_RE.search(title):
                continue
            current["events"].append(
                {
                    "time": f"{current_hour}:{minute}",
                    "title": title,
                    "venue": venue,
                    "slug": venue_slug(venue),
                    "mod": event_modifier(title),
                }
            )
    return venues, days


def render_schedule_widget(csv_text: str) -> str:
    venues, days = parse_schedule_days(csv_text)
    if not days:
        return '<p class="text-center text-muted py-4">Расписание недоступно.</p>'
    tabs: list[str] = []
    chips = [
        '<button type="button" class="hf-schedule__venue-chip is-active" data-venue="">Все площадки</button>'
    ]
    for venue in venues:
        slug = venue_slug(venue)
        chips.append(
            f'<button type="button" class="hf-schedule__venue-chip" data-venue="{html_lib.escape(slug)}">'
            f"{html_lib.escape(venue)}</button>"
        )
    cards: list[str] = []
    for index, day in enumerate(days):
        active = " is-active" if index == 0 else ""
        selected = "true" if index == 0 else "false"
        tabs.append(
            f'<button type="button" class="hf-schedule__day-tab{active}" role="tab" '
            f'aria-selected="{selected}" data-day="{day["iso"]}">'
            f'<span class="hf-schedule__day-short">{html_lib.escape(day["short"] or day["label"])}</span>'
            f'<span class="hf-schedule__day-date">{html_lib.escape(day["display"])}</span></button>'
        )
        hidden_day = "" if index == 0 else " is-hidden"
        for event in day["events"]:
            mod = f" {event['mod']}" if event["mod"] else ""
            cards.append(
                f'<article class="hf-schedule__event{mod}{hidden_day}" data-day="{day["iso"]}" '
                f'data-venue="{html_lib.escape(event["slug"])}">'
                f'<time class="hf-schedule__event-time">{html_lib.escape(event["time"])}</time>'
                f'<div class="hf-schedule__event-body">'
                f'<h3 class="hf-schedule__event-title">{html_lib.escape(event["title"])}</h3>'
                f'<span class="hf-schedule__event-venue">{html_lib.escape(event["venue"])}</span>'
                f"</div></article>"
            )
    return f"""<div class="hf-schedule hf-schedule--full hf-schedule--ready" data-hf-archive-schedule>
  <div class="hf-schedule__days" role="tablist">{"".join(tabs)}</div>
  <div class="hf-schedule__venues">{"".join(chips)}</div>
  <div class="hf-schedule__events hf-schedule__events--timeline">{"".join(cards)}</div>
  <p class="hf-schedule__empty text-muted text-center py-4 is-hidden">Нет событий для выбранных фильтров.</p>
</div>"""


def copy_schedule_assets() -> None:
    css_src = REPO / "legacy/wordpress-theme/hanumanfest/assets/css/hf-schedule.css"
    css_dst = SITE / "wp-content/themes/hanumanfest/assets/css/hf-schedule.css"
    if css_src.is_file():
        css_dst.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(css_src, css_dst)
    extra_css = SITE / "wp-content/themes/hanumanfest/assets/css/hf-archive-schedule.css"
    extra_css.write_text(
        """.hf-schedule__event.is-hidden,
.hf-schedule__empty.is-hidden {
  display: none;
}

.hf-schedule-page {
  max-width: 52rem;
}

body.is-schedule-page header#header {
  position: relative;
  background: #fff;
}

body.is-schedule-page .page-content {
  padding-top: 2rem;
  max-width: 52rem;
}
""",
        encoding="utf-8",
    )
    js_dst = SITE / "wp-content/themes/hanumanfest/assets/js/hf-archive-schedule.js"
    js_dst.parent.mkdir(parents=True, exist_ok=True)
    js_dst.write_text(
        """(function () {
  const root = document.querySelector('[data-hf-archive-schedule]');
  if (!root) return;
  const events = Array.from(root.querySelectorAll('.hf-schedule__event'));
  const empty = root.querySelector('.hf-schedule__empty');
  const dayTabs = root.querySelectorAll('.hf-schedule__day-tab');
  const venueChips = root.querySelectorAll('.hf-schedule__venue-chip');
  const state = {
    day: (dayTabs[0] && dayTabs[0].dataset.day) || '',
    venue: ''
  };

  function apply() {
    let visible = 0;
    events.forEach((card) => {
      const matchDay = card.dataset.day === state.day;
      const matchVenue = !state.venue || card.dataset.venue === state.venue;
      const show = matchDay && matchVenue;
      card.classList.toggle('is-hidden', !show);
      if (show) visible += 1;
    });
    if (empty) empty.classList.toggle('is-hidden', visible > 0);
    dayTabs.forEach((tab) => {
      const on = tab.dataset.day === state.day;
      tab.classList.toggle('is-active', on);
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    venueChips.forEach((chip) => {
      chip.classList.toggle('is-active', (chip.dataset.venue || '') === state.venue);
    });
  }

  dayTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      state.day = tab.dataset.day || '';
      apply();
    });
  });
  venueChips.forEach((chip) => {
    chip.addEventListener('click', () => {
      state.venue = chip.dataset.venue || '';
      apply();
    });
  });
  apply();
})();
""",
        encoding="utf-8",
    )


def write_schedule_page(csv_text: str) -> None:
    inner = render_schedule_widget(csv_text)
    copy_schedule_assets()
    html = f"""<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light only">
<title>Расписание — архив Хануман Фест 2026</title>
<meta name="description" content="Расписание Хануман Фест 2026 — архив программы прошедшего фестиваля.">
<link rel="canonical" href="{ARCHIVE_HOST}/schedule/">
<meta name="robots" content="index,follow">
<meta property="og:locale" content="ru_RU">
<meta property="og:type" content="website">
<meta property="og:title" content="Расписание — архив Хануман Фест 2026">
<meta property="og:description" content="Расписание Хануман Фест 2026 — архив программы прошедшего фестиваля.">
<meta property="og:url" content="{ARCHIVE_HOST}/schedule/">
<meta property="og:site_name" content="Хануман Фест 2026">
<meta property="og:image" content="/wp-content/uploads/2025/10/logo-hanuman.png">
<link rel="icon" href="/wp-content/uploads/2025/10/cropped-logo-hanuman-150x150.png" sizes="32x32">
<link rel="stylesheet" href="/assets/vendor/bootstrap.min.css">
<link rel="stylesheet" href="/assets/vendor/fonts-montserrat.css">
<link rel="stylesheet" href="/wp-content/themes/hanumanfest/style.css">
<link rel="stylesheet" href="/wp-content/themes/hanumanfest/assets/css/hf-schedule.css">
<link rel="stylesheet" href="/wp-content/themes/hanumanfest/assets/css/hf-archive-schedule.css">
</head>
<body class="is-schedule-page">
<header id="header" class="header-background--white">
  <nav class="container d-flex align-items-center justify-content-between py-2">
    <div class="nav-logo">
      <a href="/" class="custom-logo-link" rel="home">
        <img src="/wp-content/uploads/2025/10/logo-hanuman.png" class="custom-logo" alt="Хануман Фест" width="64" height="64">
      </a>
    </div>
    <a href="/#infoblocks" class="nav-link d-none d-md-block">О фестивале</a>
    <a href="/#program" class="nav-link d-none d-md-block">Программа</a>
    <a href="/#guests" class="nav-link d-none d-md-block">Специальные гости</a>
    <a href="/#masters" class="nav-link d-none d-md-block">Мастера и практики</a>
    <a href="/#cost" class="nav-link d-none d-md-block">Ценность</a>
    <a href="{LIVE_2027}/" class="nav-link d-none d-md-block">Регистрация</a>
    <a href="/#contacts" class="nav-link d-none d-md-block">Контакты</a>
    <button class="navbar-toggler d-md-none ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#mobileMenu" aria-controls="mobileMenu" aria-expanded="false" aria-label="Переключить меню">
      <span class="hamburger"><span></span><span></span><span></span></span>
    </button>
  </nav>
  <div class="mobile-menu-overlay"></div>
  <div class="collapse mobile-menu p-4 d-md-none" id="mobileMenu">
    <ul class="nav flex-column gap-3 mobile-menu-list">
      <li><a href="/#infoblocks"><span>О фестивале</span></a></li>
      <li><a href="/#program"><span>Программа</span></a></li>
      <li><a href="/#guests"><span>Специальные гости</span></a></li>
      <li><a href="/#masters"><span>Мастера и практики</span></a></li>
      <li><a href="/#cost"><span>Ценность</span></a></li>
      <li><a href="{LIVE_2027}/"><span>Регистрация</span></a></li>
      <li><a href="/#contacts"><span>Контакты</span></a></li>
    </ul>
  </div>
</header>
<main class="page-content">
  <div class="container hf-schedule-page">
    <h1 class="text-brand-main text-center mb-3">Программа фестиваля</h1>
    <p class="text-center text-muted mb-4">26–28 июня 2026 · архив Хануман Фест</p>
    {inner}
    <p class="hf-schedule__note text-muted text-center small mt-4 mb-0">Возможны корректировки в расписании и расширение программы.</p>
  </div>
</main>
<footer class="site-footer" style="background-image: url('/wp-content/uploads/2025/10/KxUveqx2_ySkKL1uodMLRlPX-AHveG67FW4hOk7tRbAIMeBeq5k8Rhc_QzrWH5dek6rzlxxbjUq7lY9XEafi9XBy.jpg');">
  <div class="footer-overlay"></div>
  <div class="footer-container">
    <div class="footer-right">
      <nav class="footer-menu">
        <ul class="footer-menu-items">
          <li><a href="/политика-возвратов/">Политика возвратов</a></li>
          <li><a href="/публичная-оферта/">Публичная оферта</a></li>
          <li><a href="/политика-конфиденциальности/">Политика конфиденциальности</a></li>
        </ul>
      </nav>
      <div class="footer-contacts" id="contacts">
        <a href="tel:73433858370">+7 (343) 385-83-70</a>
        <a href="tel:79222116118">+7 922 211 61 18</a>
        <a href="mailto:hanumanfest@gmail.com">hanumanfest@gmail.com</a>
      </div>
      <div class="footer-socials">
        <a href="https://vk.com/hanumanyoga" class="footer-social footer-vk" target="_blank" rel="noopener"><img src="/wp-content/themes/hanumanfest/assets/icons/vk.svg" alt="VK"></a>
        <a href="https://www.facebook.com/hanumanyoga.ru/" class="footer-social footer-fb" target="_blank" rel="noopener"><img src="/wp-content/themes/hanumanfest/assets/icons/fb.svg" alt="FB"></a>
        <a href="https://www.instagram.com/hanuman_yoga.ru/" class="footer-social footer-inst" target="_blank" rel="noopener"><img src="/wp-content/themes/hanumanfest/assets/icons/inst.svg" alt="INST"></a>
        <a href="https://t.me/Hanuman_ekb" class="footer-social footer-tg" target="_blank" rel="noopener"><img src="/wp-content/themes/hanumanfest/assets/icons/tg.svg" alt="TG"></a>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© 2026 Хануман Фест</p>
    <p>ИП Сараев Антон Валерьевич<br><br>ОГРНИП 304662518300032<br>ИНН 662504951300</p>
  </div>
</footer>
<script src="/assets/vendor/bootstrap.bundle.min.js"></script>
<script src="/wp-content/themes/hanumanfest/assets/js/script.js"></script>
<script src="/wp-content/themes/hanumanfest/assets/js/hf-archive-schedule.js"></script>
</body>
</html>
"""
    dest = SITE / "schedule" / "index.html"
    dest.parent.mkdir(parents=True, exist_ok=True)
    dest.write_text(html, encoding="utf-8")
    (SITE / "schedule" / "schedule.csv").write_text(csv_text, encoding="utf-8")


def vendor_bootstrap_and_fonts() -> None:
    vendor = SITE / "assets" / "vendor"
    vendor.mkdir(parents=True, exist_ok=True)
    css, _, _ = fetch("https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css")
    (vendor / "bootstrap.min.css").write_bytes(css)
    js, _, _ = fetch("https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js")
    (vendor / "bootstrap.bundle.min.js").write_bytes(js)
    font_css, _, _ = fetch(
        "https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap"
    )
    text = font_css.decode("utf-8", "replace")
    mapping: dict[str, str] = {}
    for i, url in enumerate(sorted(collect_css_urls(text, "https://fonts.googleapis.com/"))):
        if "fonts.gstatic.com" not in url:
            continue
        ext = Path(urllib.parse.urlsplit(url).path).suffix or ".woff2"
        name = f"montserrat-{i}{ext}"
        try:
            data, _, _ = fetch(url)
            (vendor / name).write_bytes(data)
            mapping[url] = f"/assets/vendor/{name}"
        except Exception:
            pass
    for remote, local in mapping.items():
        text = text.replace(remote, local)
    (vendor / "fonts-montserrat.css").write_text(text, encoding="utf-8")


def write_robots_and_sitemap(page_paths: list[str]) -> None:
    robots = (
        "User-agent: *\n"
        "Allow: /\n\n"
        f"Sitemap: {ARCHIVE_HOST}/sitemap.xml\n"
    )
    (SITE / "robots.txt").write_text(robots, encoding="utf-8")
    urls = [canonical_for("/")]
    for p in page_paths:
        if p not in {"/", ""} and keep_archive_path(p):
            urls.append(canonical_for(p))
    urls.append(canonical_for("/schedule/"))
    seen: set[str] = set()
    items: list[str] = []
    for u in urls:
        if u not in seen:
            seen.add(u)
            items.append(f"  <url><loc>{u}</loc></url>")
    xml = (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
        + "\n".join(items)
        + "\n</urlset>\n"
    )
    (SITE / "sitemap.xml").write_text(xml, encoding="utf-8")


def write_urls_list(pages: list[str], notes: list[str]) -> None:
    lines = [
        "# Публичные страницы архива 2026 (снимок WP)",
        "# Хост: https://хануманфест.рф (punycode xn--80aap0aec3aidne.xn--p1ai)",
        "",
        *pages,
        "",
        "# Заметки",
        *notes,
        "",
    ]
    (ROOT / "urls.txt").write_text("\n".join(lines), encoding="utf-8")


def sanitize_existing_html() -> None:
    for html_path in SITE.rglob("*.html"):
        html = html_path.read_text("utf-8")
        html_path.write_text(strip_runtime_junk(html), encoding="utf-8")


def main() -> int:
    if "--sanitize-only" in sys.argv:
        sanitize_existing_html()
        print("sanitize-only:", SITE)
        return 0
    if "--schedule-only" in sys.argv:
        csv_path = SITE / "schedule" / "schedule.csv"
        write_schedule_page(csv_path.read_text(encoding="utf-8"))
        print("schedule-only:", SITE / "schedule")
        return 0

    SITE.mkdir(parents=True, exist_ok=True)
    notes = [
        "Форма Forminator id=156 на главной — заменена на CTA 2027.",
        "API расписания https://апи.хануманфест.рф — 404; на живой главной виджета не было.",
        f"Подробное расписание: Google Sheet {SHEET_ID} (было clck.su/peQdI) → /schedule/.",
        "Yoast sitemap на живом сайте указывает на punycode того же домена хануманфест.рф.",
        "Публичные страницы архива: главная, /schedule/, питание, политики, оферта.",
        "Jivo, payment.js, Forminator, wp-json ссылки из HTML вырезаны.",
    ]
    print("→ список страниц")
    page_urls = gather_page_urls()
    print(f"  {len(page_urls)} URL")
    asset_urls: set[str] = set()
    saved_paths: list[str] = []
    for url in page_urls:
        path = origin_path(url)
        dest = local_page_path(path)
        try:
            data, ctype, status = fetch(url)
        except urllib.error.HTTPError as exc:
            print(f"  skip {exc.code} {url}")
            continue
        html = data.decode("utf-8", "replace")
        if "text/html" not in ctype and "<html" not in html.lower():
            print(f"  skip non-html {url}")
            continue
        collector = AssetCollector()
        try:
            collector.feed(html)
        except Exception:
            pass
        asset_urls.update(collector.urls)
        asset_urls.update(re.findall(r"""(?:src|href|poster)=["']([^"']+)["']""", html, flags=re.I))
        asset_urls.update(re.findall(r"url\(\s*['\"]?([^'\")]+)['\"]?\s*\)", html, flags=re.I))
        html = sanitize_html(html, path)
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_text(html, encoding="utf-8")
        saved_paths.append(path)
        print(f"  {status} {path} → {dest.relative_to(SITE)}")

    print("→ vendor bootstrap/fonts")
    vendor_bootstrap_and_fonts()

    print("→ ассеты")
    resolved: set[str] = set()
    queue: list[str] = []
    for raw in asset_urls:
        if not raw or raw.startswith("data:") or raw.startswith("mailto:") or raw.startswith("tel:"):
            continue
        absu = raw if raw.startswith("http") or raw.startswith("//") else urllib.parse.urljoin(ORIGIN_PUNY + "/", raw)
        if absu not in resolved:
            resolved.add(absu)
            queue.append(absu)
    css_follow: list[str] = []
    with ThreadPoolExecutor(max_workers=8) as pool:
        futs = {pool.submit(download_asset, u): u for u in queue}
        for fut in as_completed(futs):
            url, dest, how = fut.result()
            if how.startswith("err"):
                print(f"  ! {how} {url}")
            elif dest and dest.suffix.lower() == ".css" and how in {"download", "local"}:
                css_follow.append(str(dest))

    extra_css_urls: set[str] = set()
    for css_file in css_follow:
        p = Path(css_file)
        text = p.read_text("utf-8", "replace")
        extra_css_urls.update(collect_css_urls(text, ORIGIN_PUNY + "/" + str(p.relative_to(SITE))))
    with ThreadPoolExecutor(max_workers=8) as pool:
        list(pool.map(download_asset, extra_css_urls))

    theme_src = REPO / "legacy/wordpress-theme/hanumanfest/assets"
    theme_dst = SITE / "wp-content/themes/hanumanfest/assets"
    if theme_src.is_dir():
        shutil.copytree(
            theme_src,
            theme_dst,
            dirs_exist_ok=True,
            ignore=lambda _d, names: [n for n in names if n == "payment.js"],
        )

    print("→ расписание")
    csv_url = f"https://docs.google.com/spreadsheets/d/{SHEET_ID}/export?format=csv&gid={SHEET_GID}"
    csv_bytes, _, _ = fetch(csv_url)
    csv_text = csv_bytes.decode("utf-8-sig", "replace")
    write_schedule_page(csv_text)

    write_robots_and_sitemap(saved_paths)
    write_urls_list(page_urls, notes)
    sanitize_existing_html()
    print("готово:", SITE)
    return 0


if __name__ == "__main__":
    sys.exit(main())
