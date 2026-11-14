# Source overview tiles

The global map uses a transparent, information-dense source overlay through zoom 10,
then switches to the existing detailed vector markers. The additional zooms 9 and 10
are raster levels. Personal contributor maps keep their vector layer.

Coverage comes from every public, non-redirected source. The style is the profile
selected in the raster lab (`source-raster-profile.json`), stored as
`source_raster.style` in `config/source_raster.php`: an explicit diameter (CSS px)
and opacity for each color at each zoom 0–10, plus the layer draw order. Blue marks
all share one diameter; sources on the same pixel combine as `1 - (1 - opacity)^n`,
and neighboring marks overlap by ordinary source-over alpha, with no density shading
or compaction. Green, yellow and red keep their maximum coverage per color, and each
complete color layer is composited in the configured order. Coincident conditions
form tiny multicolor groups instead of a majority color or an RGB average. Not-found
sources remain excluded from the overview, consistent with the previous distant view.

`RasterRenderer` is a port of the lab's `render-worker.js` and produces byte-identical
pixels for the same cells. It writes 8-bit RGBA PNGs itself (zlib only), because GD's
7-bit alpha cannot represent the faint world-view blue (0.5% opacity). Its gutter
(`RasterRenderer::GUTTER`, 8 physical px) must cover the largest mark's reach; a test
enforces this. After changing the style, run `sources:raster-restyle`.

Tiles are 512 physical pixels displayed as 256 CSS pixels. Small marks consequently
remain useful on retina screens. The Layers menu has a keyboard-accessible opacity
slider, saved locally in the browser.

## Running it

Required PHP extensions are `zlib`, `pdo_mysql`, and `pdo_sqlite` (`gd` only for tests). SQLite is an embedded
cache here, not another database server. These extensions are already available on
the development Mac; no GIS software, GPU, or new package installation was needed.

```sh
php artisan sources:raster-build
```

This builds the complete numeric snapshot, renders populated/edge tiles at zooms
0–10, and atomically publishes the new manifest. The previous snapshot remains
available throughout. Progress and measured time, bytes and PHP peak memory are printed.

To limit the raster range or skip prewarming PNGs:

```sh
php artisan sources:raster-build --max-zoom=8
php artisan sources:raster-build --no-render
```

`--no-render` still prepares every numeric zoom and publishes a usable layer. An image
cache miss draws only the requested tile, never a complete dataset. Type/report/route
variants are always cached on demand. Before the first build, the frontend falls back
to the existing vector overview and retries discovery.

The normal Laravel scheduler runs the following every minute:

```sh
php artisan sources:raster-refresh
```

The deployment must run its existing `schedule:run` cron (or `schedule:work` locally).
Report changes, moves, hides/restores, merges and OSM import changes feed the existing
tile invalidation hooks, which now also enqueue raster coordinates. No daily full
rebuild is needed for current score semantics. After changing the renderer or colors,
reuse the numeric snapshot and publish fresh image URLs:

```sh
php artisan sources:raster-restyle
php artisan sources:raster-restyle --no-render
```

The default prewarms images before publication; `--no-render` publishes immediately
and draws tiles on demand. Existing immutable images stay unchanged. Numeric pixel
or zoom conventions still require a full build.

## Data and serving

Generated data lives under `storage/app/source-raster`, configurable with
`SOURCE_RASTER_PATH`. It is disposable derived data and is not committed. Keep it
writable by both the CLI worker and the web process. Do not expose the SQLite files
through a public symlink.

- `/source-tiles.json` discovers the current revision and zoom range without caching.
- `/source-tiles/{revision}/{z}/{x}/{y}.png` returns an immutable PNG, including a
  transparent image for valid empty tiles. These public endpoints do not start
  sessions or emit session cookies.
- `types=0..63` selects any union of the six source types, and `reports=1` selects
  sources with visible reports. `polygon=<hash>` uses an existing route-buffer polygon.
- Route filtering tests original source coordinates before aggregation, including
  polygon holes and boundaries. It does not clip a global PNG to approximate a route.
- Browser caches, and a CDN if configured, can retain the revision-specific PNGs.
  The current implementation sends files through Laravel; cache misses are also
  handled there. It does not require new Nginx routing.

A SQLite snapshot stores original coordinates and sparse numeric cells per type and
condition at each zoom. The builder streams sources and sums numeric child cells;
it never downsamples colored images or constructs a world-sized canvas. Small padded
canvases prevent marks from disappearing at tile boundaries, including the dateline.

Refresh copies the current numeric snapshot, rereads only affected finest pixels,
updates their ancestors and renders affected images. Old PNGs/SQLite files remain
unchanged. Claimed work is persisted in `processing.jsonl` until successful publication;
an interrupted worker can replay it safely. New arrivals remain in `dirty.jsonl`.
Build/restyle/refresh workers share a lock, so do not run them concurrently on different hosts
against the same cache directory.

Each new revision starts its own image cache. Unchanged images are cheap to regenerate
on demand, but their URLs also change; this is a deliberate cache/storage tradeoff.
Foreground clients discover new revisions every 60 seconds, pause while hidden, and
refresh on return. With the minute scheduler, normal displayed-update latency can be
up to approximately two minutes even when processing itself takes less than a second.

Retention defaults to six generations, a ten-minute grace period, and a 24-hour age
limit. The active and previous generations are always preserved. Cleanup happens on
publication. A long-sleeping client whose old revision expired rediscovers the manifest.

## Local measurements

Measured 28 October 2026 on the Apple M4 / 32 GB development Mac, using the older
local snapshot of **1,369,020 public sources**. Database and filesystem caches were warm;
browser checks and tests were also running. These are local timings, not production
server guarantees.

The following full-pyramid and refresh measurements used the original visual recipe,
before the zoom-aware renderer correction on 29 October; they are not a benchmark
of a complete pyramid with the revised style.

The initial complete run through zoom 10 prepared 6,786,965 numeric cells in **13.3 s**
and rendered **73,440 PNGs** in **457.3 s**, **470.7 s total** on one PHP worker.
PNG payload was **390.8 MiB**, and the numeric snapshot was **219.3 MiB**. PHP peak
allocation was **99.5 MiB** under the default 128 MiB limit. A final clean build after
the padded-canvas fix took **13.0 s preparation + 596.3 s PNG generation = 609.3 s**,
again producing 73,440 images / **390.8 MiB**, with the same memory peak. The longer
run included concurrent browser checks and benchmarks on the fanless laptop.
Filesystem allocation is higher than payload size because there are many small files.

An isolated copy of that full snapshot was used to correct one cached condition to
its actual database value. The genuine refresh path took **0.394 s**, copied the
219 MiB snapshot and generated **11 PNGs / 729 KiB**. PHP peak allocation was
**44.5 MiB**. Application data and the live snapshot were unchanged by this benchmark.

Exact route filtering against the real snapshot took **0.37 s** for a 5,579-vertex
corridor and **0.43 s** for a near-limit 49,999-vertex corridor, selecting 42 sources.
These timings include geometry preparation, indexed source selection and aggregation,
and exclude PNG encoding and HTTP overhead.

Cold PNG rendering was also verified with an explicit `memory_limit=128M`: a world
tile took **53 ms / 42.5 MiB PHP peak**, the densest z8 tile **44 ms / 40.5 MiB**, and
a world tile filtered by a 49,999-vertex route **598 ms / 66.5 MiB**. All images were
valid transparent 512×512 PNGs. Twenty-four concurrent cold filtered HTTP requests
all returned 200 without session cookies, with a maximum response time of **1.16 s**.

On 29 October, the revised zoom-aware renderer was measured in a fresh PHP process
per tile with `memory_limit=128M`, against the same read-only full snapshot. SQLite
selection, drawing and PNG encoding took **43 ms** for the world tile, **37–42 ms**
for the densest zoom-8/9/10 tiles, **8–9 ms** for isolated-source tiles and **156 ms**
for the densest zoom-4 tile. These exclude filesystem writes and HTTP overhead.
Maximum observed managed PHP allocation was **62.5 MiB**, and maximum process RSS
was **103 MiB**. PNGs ranged from **4.7 KiB** for isolated sources to **266 KiB** for
the densest midzoom tile. A complete prewarm with this style has not been timed.

`sources:raster-restyle --no-render` published the corrected style by reusing the
existing snapshot in under **1 ms** of command-internal work, excluding Laravel
startup. Requested images then rendered on demand. The local browser review has
63 before/after screenshots spanning requested zooms 0–18 and a known-source sequence
near Moscow. At the 1440×1000 review viewport, requested zooms 0–2 clamp to the map's
actual minimum of z2.49; the gallery labels that explicitly.

## Public tuning trial

The disposable playground lives in `storage/app/public/raster-lab/`. This directory
is Git-ignored, so a normal application deployment does not publish it. It can be
deployed independently of the unfinished raster application changes: copy only its
static frontend, manifest and referenced binary files into the same public-storage
path on the Rodnik host. No database connection, scheduler or raster build command
is required for the playground. Its data is a fixed public-source snapshot.

The local helper `storage/app/source-raster/lab/package-release.py` validates the
manifest and produces an allowlisted ZIP in its `releases/` directory, including
deployment instructions. Upload only the archive contents; the surrounding private
`storage/app/source-raster/` tree contains database snapshots and is not a public
deployment artifact. The page is served at `/storage/raster-lab/index.html` through
the existing public-storage symlink. Its main-map links assume the Rodnik origin.

Each visitor's choices persist in that browser's localStorage. Visitors submit their
preferred complete profile by sending the JSON from **Copy settings**; the page has
no central collection endpoint. **Import settings** transfers profiles between
browsers and origins, including from `rodnik.test` to the public host.

The latest playground uses explicit size/opacity values for every color and zoom,
adjustable draw order, and natural alpha overlap for blue sources. The selected
profile (Barcelona, v3, 14 November 2026) is now implemented by the PHP renderer.
Rendering the lab's exported cells for 10 tiles at zooms 0–10 (world, Barcelona,
Moscow, Crimea, Lycian Way) through both the lab worker and `RasterRenderer` gave
zero differing pixels, and the lab export matched snapshot cell counts. The disposable
public lab and temporary review assets can now be removed.

## Verification

```sh
vendor/bin/pest tests/Unit/RasterSnapshotTest.php tests/Unit/RasterPolygonTest.php tests/Feature/RasterPyramidTest.php tests/Feature/RasterRendererZoomTest.php tests/Feature/RasterRestyleTest.php tests/Feature/SourceRasterHttpTest.php
node --test --test-concurrency=1 tests/Unit/*.mjs
npm run build
```

Tests cover complete numeric counts, colors, transparent backgrounds, dense/sparse
marks after retina downsampling, zoom-specific styles, tile seams, four-tile corners,
dateline wrapping, accurate type/report/route filtering, holes and
boundaries, incremental edits, immutable snapshots, durable queue replay, concurrent
invalidations, retention, HTTP caching, opacity persistence, zoom handover and manifest
polling and immutable restyling. Visual review additionally checks world, regional,
zoom-9/10 and mobile views, plus every integer step through the detailed street view.
