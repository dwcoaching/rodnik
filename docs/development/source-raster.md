# Source overview tiles

The global map uses a transparent, information-dense source overlay through zoom 10,
then switches to the existing detailed vector markers. The additional zooms 9 and 10
are raster levels. Personal contributor maps keep their vector layer.

Coverage comes from every public, non-redirected source. A style is an explicit
diameter (CSS px) and opacity for each color at each zoom 0–10, plus the layer draw
order. The published style is stored in the raster manifest; until an admin saves
one, the default in `config/source_raster.php` applies (the profile selected in the
raster lab, `source-raster-profile.json`). Blue marks
all share one diameter; sources on the same pixel combine as `1 - (1 - opacity)^n`,
and neighboring marks overlap by ordinary source-over alpha, with no density shading
or compaction. Green, yellow and red keep their maximum coverage per color, and each
complete color layer is composited in the configured order. Coincident conditions
form tiny multicolor groups instead of a majority color or an RGB average. Not-found
sources remain excluded from the overview, consistent with the previous distant view.

`RasterRenderer` is a port of the lab's `render-worker.js` and produces byte-identical
pixels for the same cells. It writes 8-bit RGBA PNGs itself (zlib only), because GD's
7-bit alpha cannot represent the faint world-view blue (0.5% opacity). Its gutter
(`RasterRenderer::GUTTER`, 16 physical px) covers the largest diameter a style may
use (`MAX_DIAMETER`, 14 CSS px); a test enforces this.

Tiles are 512 physical pixels displayed as 256 CSS pixels. Small marks consequently
remain useful on retina screens. The Layers menu has a keyboard-accessible opacity
slider, saved locally in the browser.

## How it works

1. **Counts.** `storage/app/source-raster/index.sqlite` holds one table of counts:
   how many sources of each type and condition fall on each pixel, at every zoom
   0–10. Zoom 10 is counted from MySQL; each lower zoom is the sum of the one above.
   Individual sources are not stored, and MySQL gets no new tables.
2. **Tiles.** `tiles/{revision}/{z}/{x}/{y}.png` holds the unfiltered tiles. A build
   or restyle draws all of them under a new revision, then switches the manifest.
3. **Edits.** Source and report changes append coordinates to `dirty.jsonl` through
   the existing tile invalidation hooks. Every minute `sources:raster-refresh`
   recounts those pixels from MySQL, updates the counts in place and redraws only
   the tiles that show them: 11 per changed pixel (one per zoom), up to 44 near a
   tile corner. The revision and every other tile stay as they are.
4. **Map.** The browser reads `/source-tiles.json` every 60 seconds, shows the raster
   layer through zoom 10 and the vector markers beyond. Without a build, or when a
   type, reports-only or along-route filter is active, it uses the vector overview.

## Turning it on

The raster overview is **off by default**. Building tiles does not change what
visitors see: `/source-tiles.json` answers 503 until a superadmin presses "Switch to
raster" in the Filament Control Panel, and maps keep the vector overview. The same
button switches back; open maps follow within about a minute. The lab page works in
both states, so a style can be tuned before the switch. The switch is the file
`enabled` in the raster directory.

## Running it

Required PHP extensions are `zlib`, `pdo_mysql`, and `pdo_sqlite` (`gd` only for
tests). No GIS software, GPU, or new package is needed.

```sh
php artisan sources:raster-build            # count every source and draw every tile
php artisan sources:raster-restyle          # redraw every tile from the existing counts
php artisan sources:raster-refresh          # apply queued edits; run by the scheduler
```

`--no-render` on build or restyle publishes at once and draws tiles on first request.
The deployment must run its existing `schedule:run` cron (or `schedule:work` locally);
the scheduler runs the refresh every minute. While a build or restyle is running, the
refresh reports that it is busy and the edits stay queued.

Generated data lives under `storage/app/source-raster`, configurable with
`SOURCE_RASTER_PATH`. It is disposable derived data and is not committed. Keep it
writable by both the CLI worker and the web process, and do not expose it through a
public symlink.

## Tuning the style (admins)

`/admin/raster-lab` is a separate page for admins, with a map of its own. Its side
panel edits size and opacity for each color at one zoom (it follows the map's zoom
unless "follow map" is off), the draw order, and can copy a zoom's values to the
next. While there are unsaved edits, the raster layer is drawn by
`/admin/source-raster/preview/{z}/{x}/{y}.png` with the draft values, using the same
renderer as the published tiles, so the preview is exactly what will be generated.
The real vector markers are shown above zoom 10 as on the main map, and can also be
drawn over the raster from zoom 8 for comparison. Visitors keep seeing the published
style, and the main map contains none of this.

**Generate tiles** saves the draft as `style.pending.json`. The next scheduled refresh
redraws every tile with it and publishes a new revision; the page shows "Queued",
then "Drawing", then switches to the new tiles. Locally, without a scheduler, run
`php artisan sources:raster-refresh` after pressing the button. **Copy / paste**
moves a style between environments as JSON.

The page is disposable. To remove it, delete `resources/js/rasterLab/`, its entry in
`vite.config.js`, `resources/views/admin/raster-lab.blade.php`, the `admin/raster-lab`
route, and the lab tests in `SourceOverviewTest.mjs` and `SourceRasterHttpTest.php`.
`SourceRasterStyleController` and its `admin/source-raster` routes serve only this
page and can go with it; styles can then still be changed in
`config/source_raster.php` followed by `sources:raster-restyle`.

## Serving

- `/source-tiles.json` returns the current revision, zoom range and style, uncached.
- `/source-tiles/{revision}/{z}/{x}/{y}.png` serves a stored tile with
  `max-age=60` and `Last-Modified`, so browsers revalidate and get a 304 for tiles
  that did not change. A missing tile of the current revision is drawn and stored;
  a missing tile of an older revision is a 404. These endpoints start no session.
- `/source-tiles/{z}/{x}/{y}.png` is the stable XYZ template for external maps.
  Its uncached redirect follows the currently published revision while raster is
  enabled. All public raster tiles include all source types.
- Only the current and the previous revision's tiles are kept; older ones are
  deleted when a revision is published.
- A map that is already open does not reload tiles when a source changes; it shows
  the change after a reload or when the tile is requested again. A single failed
  tile makes the map check for a newer revision; only a failing manifest switches
  it to the vector overview.

## Failure handling

Queued coordinates are moved to `processing.jsonl` before work starts and removed
only after the tiles are redrawn. If a run dies in between, the next run redraws the
tiles for those coordinates even when the counts were already saved. Coordinates
arriving during a run stay in `dirty.jsonl` for the next one. Recounting a pixel is
idempotent, so replays and duplicates are harmless. A full build can run at any
time; edits made during it remain queued and are recounted afterwards.

## Local measurements

Measured 14 November 2026 on the Apple M4 / 32 GB development Mac with
**1,390,334 public sources**, one PHP worker, default `memory_limit=128M`. These are
local timings, not production guarantees.

- **Full build:** 11.0 s to count sources into 6,897,350 cells, then 174 s to draw
  **77,603 tiles**; 185 s in total. The snapshot is 162 MB and the PNGs 485 MiB
  (700 MB on disk, as there are many small files).
- **Restyle from the admin panel:** 227 s for 77,585 tiles with a deliberately heavy
  test style (9 CSS px blue marks); the default style is closer to the build figure.
- **Edits:** 233 queued coordinates were recounted in 0.6 s; one pixel had changed
  and 41 tiles were redrawn.
- **Memory:** a build peaks at about 82 MiB in use (117.5 MiB allocated). Drawing
  the densest tile adds about 20 MiB to a request.
- **Serving:** a stored tile answers in a few milliseconds and revalidates with a
  304; a filtered tile at zoom 10 took 27 ms. A preview of a 20-tile view took
  about 0.5 s on the single-threaded development server.
- **Lab parity:** rendering the lab's exported cells for tiles at zooms 0, 2, 4, 6
  and 10 through the lab worker and `RasterRenderer` gave zero differing pixels.

## Verification

```sh
vendor/bin/pest tests/Unit/RasterSnapshotTest.php tests/Feature/RasterPyramidTest.php tests/Feature/RasterRendererZoomTest.php tests/Feature/RasterRestyleTest.php tests/Feature/SourceRasterHttpTest.php
node --test --test-concurrency=1 tests/Unit/*.mjs
npm run build
```

Tests cover complete counts, colors, transparent backgrounds, configured sizes and
opacities, 8-bit alpha, draw order, tile seams, four-tile corners, dateline wrapping,
type and report filtering, in-place edits that redraw only the affected tiles, queue
replay after a failed redraw, edits arriving during a refresh, saved styles applied by
the refresh, style validation, removal of old tile sets, HTTP caching and
revalidation, the admin-only lab page and style endpoints, the lab panel, opacity persistence, zoom
handover, manifest polling and tolerance of single tile errors.
