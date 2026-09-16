"""Generate the WordPress.org directory assets.

The mark is a plain geometric biscuit: a disc with a few chips. Deliberately
generic — the previous artwork was the Sesame Street character, which is the
trademark that forced the rename, and an icon that merely resembles it would
put the submission back where it started.

Sizes come from the plugin handbook:
  icon-128x128.png, icon-256x256.png
  banner-772x250.png, banner-1544x500.png
"""
import io
import os
import math
import random

from PIL import Image, ImageDraw, ImageFont

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "assets")
os.makedirs(OUT, exist_ok=True)

INK = (17, 30, 51)
CREAM = (242, 226, 205)
CHIP = (92, 61, 40)
ACCENT = (232, 122, 65)
PAPER = (255, 255, 255)

FONT_CANDIDATES = [
    r"C:\Windows\Fonts\segoeuib.ttf",
    r"C:\Windows\Fonts\arialbd.ttf",
    r"C:\Windows\Fonts\calibrib.ttf",
]
FONT_REGULAR = [
    r"C:\Windows\Fonts\segoeui.ttf",
    r"C:\Windows\Fonts\arial.ttf",
    r"C:\Windows\Fonts\calibri.ttf",
]


def font(paths, size):
    for path in paths:
        if os.path.exists(path):
            return ImageFont.truetype(path, size)
    return ImageFont.load_default()


def draw_biscuit(draw, cx, cy, radius, behind, scale=1.0):
    """A disc with a bitten edge and a few chips."""
    draw.ellipse(
        [cx - radius, cy - radius, cx + radius, cy + radius],
        fill=CREAM,
    )

    # The bite is drawn in whatever sits behind the biscuit: ImageDraw paints
    # a transparent fill rather than clearing what is underneath, so asking for
    # alpha zero leaves a hole of the wrong colour.
    bite = radius * 0.42
    bx = cx + radius * 0.72
    by = cy - radius * 0.62
    draw.ellipse([bx - bite, by - bite, bx + bite, by + bite], fill=behind)

    # Chips, placed by hand so they never collide with the bite.
    chips = [
        (-0.38, -0.30, 0.15),
        (0.05, -0.42, 0.11),
        (-0.10, 0.12, 0.17),
        (0.38, 0.28, 0.13),
        (-0.45, 0.38, 0.11),
        (0.20, 0.55, 0.10),
    ]
    for dx, dy, size in chips:
        r = radius * size * scale
        x = cx + radius * dx
        y = cy + radius * dy
        draw.ellipse([x - r, y - r, x + r, y + r], fill=CHIP)


def make_icon(size, path):
    # Drawn at 4x and downsampled: Pillow has no antialiasing on shapes.
    factor = 4
    canvas = Image.new("RGBA", (size * factor, size * factor), (0, 0, 0, 0))
    draw = ImageDraw.Draw(canvas)

    s = size * factor
    draw.ellipse([0, 0, s - 1, s - 1], fill=INK)

    draw_biscuit(draw, s / 2, s / 2, s * 0.30, INK)

    canvas = canvas.resize((size, size), Image.LANCZOS)
    canvas.save(path)
    print("wrote", path)


def make_banner(width, height, path):
    factor = 2
    w, h = width * factor, height * factor
    canvas = Image.new("RGB", (w, h), INK)
    draw = ImageDraw.Draw(canvas)

    # A faint scatter of crumbs, fixed seed so the image is reproducible.
    random.seed(7)
    for _ in range(90):
        x = random.uniform(0, w)
        y = random.uniform(0, h)
        r = random.uniform(1.0, 3.2) * factor
        draw.ellipse([x - r, y - r, x + r, y + r], fill=(26, 42, 68))

    biscuit_cx = w * 0.845
    biscuit_r = h * 0.34
    draw_biscuit(draw, biscuit_cx, h / 2, biscuit_r, INK)

    title = font(FONT_CANDIDATES, int(h * 0.175))
    tagline = font(FONT_REGULAR, int(h * 0.088))

    left = w * 0.06
    draw.text((left, h * 0.30), "Piensa Cookie Consent", font=title, fill=PAPER)
    draw.text(
        (left, h * 0.545),
        "GDPR consent that blocks before it asks.",
        font=tagline,
        fill=(168, 184, 208),
    )
    draw.text(
        (left, h * 0.665),
        "No cloud. No CDN. Nothing leaves your server.",
        font=tagline,
        fill=(168, 184, 208),
    )

    draw.rectangle([left, h * 0.80, left + h * 0.42, h * 0.825], fill=ACCENT)

    canvas = canvas.resize((width, height), Image.LANCZOS)
    canvas.save(path)
    print("wrote", path)


make_icon(256, os.path.join(OUT, "icon-256x256.png"))
make_icon(128, os.path.join(OUT, "icon-128x128.png"))
make_banner(772, 250, os.path.join(OUT, "banner-772x250.png"))
make_banner(1544, 500, os.path.join(OUT, "banner-1544x500.png"))
