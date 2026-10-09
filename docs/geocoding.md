# Water-source reverse geocoding

Water sources link to `countries`, `regions`, and `cities` through `country_id`, `region_id`, and `nearest_city_id`. They also store `nearest_city_distance_km`, `geocoding_source`, and `geocoded_at`. Names and the country code are not copied to sources: they are read from the linked catalog rows. Country, containing region, and nearest settlement appear on the source detail view and in both JSON API resources, whose `country`, `country_ru`, `country_code`, `region`, `region_ru`, `city`, and `city_ru` keys come from those links and are null without one. Geocoding changes only derived geography; coordinates, OSM tags, reports, and user revisions remain the authority for the source itself.

## Setup and data

```sh
php artisan migrate
php -d memory_limit=256M artisan geocoding:import-catalog
php -d memory_limit=256M artisan springs:geocode --queue
php -d memory_limit=256M artisan queue:work database --queue=geocoding --timeout=60 --memory=200
```

New sources and coordinate edits resolve synchronously. Existing sources use the bulk command or Filament page. The dedicated `geocoding` queue keeps this work separate from other application jobs.

The offline default includes:

- `resources/geocoding/countries.geojson`: Natural Earth country polygons.
- `resources/geocoding/settlements.txt`: GeoNames `cities500` plus full Russia and Ukraine country extracts, filtered to current settlements and deduplicated by GeoNames ID. The bundled snapshot contains 436,514 places, including villages with small or unknown populations in those two extracts.
- `resources/geocoding/admin1CodesASCII.txt`: GeoNames region names.
- `resources/geocoding/regions.geojson`: Natural Earth administrative polygons, normalized with stable identities; its separate manifest records provenance and transformations.
- `resources/geocoding/countryInfo.txt`: GeoNames country identifiers.
- `resources/geocoding/russian-names.tsv`: current Russian alternate names from GeoNames, selected from `alternateNamesV2.zip`; its separate manifest describes selection and coverage.
- `resources/geocoding/rodnik-with-countries.json`: the supplied 8,431-source export, retained as an exact-coordinate fallback where country boundaries have no coverage.
- `resources/geocoding/manifest.json`: input URLs, versions, retrieval time, transformations, licences, and SHA-256 checksums.
- `resources/geocoding/russia-claimed-borders.geojson`: a separate final country-label policy; its own manifest records geographic scope, source provenance, and licence.

Country is determined by polygon containment and then the final Russia country-label policy below. Region is determined by containment in an administrative polygon, independently of the nearest settlement. Nearest city means the closest settlement in the catalog by great-circle distance, independently of country borders. This includes towns and villages; it does not mean the largest city in the area. `GEOCODING_CITY_SCOPE=country` optionally restricts the base search to the source's provider country. Simplified geographic boundaries and the settlement catalog set the precision and coverage of the result; coordinates without country or policy coverage remain unmatched unless the exact fallback contains them.

City coordinates use a compact sphere-based spatial index. The index is rebuilt automatically when input contents change and cached under `storage/framework/cache/geocoding`. It is safe to delete this cache. A bounded bulk chunk uses one validated snapshot; the next chunk detects replacements. Interactive lookups check input contents, including same-size replacements with the same modification time.

Update bundled geographic data with:

```sh
php artisan geocoding:update-dataset
php artisan geocoding:update-regions
php artisan geocoding:update-names
php -d memory_limit=256M artisan geocoding:import-catalog
php artisan springs:geocode --queue
```

The update command downloads pinned country boundaries and current GeoNames inputs, validates and normalizes all inputs before publication, and records attribution and checksums in the manifest. Downloads can also be supplied locally with `--countries-source`, `--cities-source`, `--admin1-source`, and repeatable `--supplement-source=RU=/path/RU.zip`; `--directory` installs into another directory. Changing data does not itself rewrite existing source labels. Recalculate all to update previously populated labels.

Choose a settlement catalog without changing the application's input filename:

```sh
php artisan geocoding:update-dataset --settlements=cities500 --supplement-countries=RU,UA
php artisan geocoding:update-dataset --settlements=cities1000 --supplement-countries=
php -d memory_limit=1G artisan geocoding:update-dataset --settlements=all --directory=/path/to/staging
```

The default is `cities500` with `RU,UA` supplements. Other comma-separated country codes can supplement either city catalog. An explicit empty supplement value disables them. `all` filters the worldwide GeoNames extract to current populated places and cannot be combined with supplements. Use a 256M PHP limit for catalog imports and workers. The complete default provider with catalog enrichment has been checked in a booted Laravel application at 128M: cold index construction took about 12.4 seconds and peaked at 118 MiB; warm validation took about 0.32 seconds. A warm Livewire manual refresh took about 0.34 seconds and peaked at 116 MiB. A 200-point snapshot averaged about 0.17 ms per lookup, excluding Spring persistence; measured timings depend on hardware and cache state. The full-world village catalog is much larger and has not been benchmarked here. Generate it in a separate directory and measure HTTP and worker memory before changing the configured paths. The [GeoNames readme](https://download.geonames.org/export/dump/readme.txt) describes the catalogs and TSV schema.

GeoNames data is adapted under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/). Natural Earth data is [public domain](https://www.naturalearthdata.com/about/terms-of-use/). Public attribution appears on the About page; see the manifest for the precise inputs and transformations.

## Catalog identities, Russian names, and public pages

Countries are matched by stable country code. Regions use `geonames-region:ID` where a reliable GeoNames identity exists; other regions retain a stable provider `source_key` and optional ISO code. Mutable administrative codes are stored separately, so code reassignment cannot change an existing region's identity or URL. Settlements are identified by their GeoNames ID and retain their original provider country/admin1 codes, even when the final configured border policy assigns their country or region differently. Settlement rows store center coordinates and population where available. Source country and region links are resolved from the source coordinates, not copied from its nearest settlement.

`geocoding:import-catalog` imports the configured files in bounded batches within a database transaction. Reimporting updates names, coordinates, and membership while retaining existing database IDs and URL slugs. Removed records become inactive; they are not deleted or reassigned to an unrelated place. Invalid or changing input rolls back the import. The CLI refuses to import while a bulk geocoding run is active, avoiding catalog foreign-key locks blocking its worker. Finish or stop the bulk run, import the catalog, then start the new recalculation. The CLI reports table progress and Russian-name coverage. Optional `--country-info`, `--russian-names`, `--countries`, `--admin1`, `--cities`, and `--regions` choose local import files.

`geocoding:update-names` streams the official worldwide `alternateNamesV2.zip`, selects Russian (`ru`) entries, excludes historic, colloquial, and ended-period names, and prefers preferred/short display names. This download is substantially larger than the installed Russian-only TSV. `--names-source` and `--country-info-source` accept local upstream files; `--directory` changes the destination. Russian is displayed where a current translation exists; otherwise the original name is displayed. Missing Russian names do not prevent a source or location page from being used. Country `RU` always retains `Russia` / `Россия` regardless of alternate names.

The installed 2026-10-02 catalog contains 250 countries, 5,260 regions, and 436,514 settlements. Russian names are available for all 250 countries, 4,804 regions, and 260,344 settlements. Configured country aliases normalize settlement membership for French overseas territories, the Caribbean Netherlands, and Svalbard while retaining their original provider country codes.

`geocoding:update-regions` downloads pinned Natural Earth administrative polygons or validates a local `--source` containing raw Natural Earth or normalized GeoJSON. `--directory` stages another installation; `--admin1-source` selects a current administrative identifier catalog. Configure `GEOCODING_REGIONS_PATH` to select the installed file. Normalized features require `source_key`, `country_code`, and `name`, with optional `geonames_id`, `iso_code`, and `name_ru`. Natural Earth's subdivision level varies by country (for example, French departments and British boroughs); these pages describe the installed administrative geography, not one globally uniform subdivision level. Normalization joins current GeoNames identities by verified ID instead of stale Natural Earth administrative codes, and gives ambiguous identities independent provider keys. The bundled snapshot has 4,577 polygons, excluding 19 unassigned residual features and clearing 28 ambiguous GeoNames references. Region membership cannot be established where the installed geometry has no coverage; a missing region remains null instead of borrowing the nearest town's region.

After any country, settlement, region, name, or policy update, import the catalog and recalculate all source links. `--missing` selects sources without a country or nearest-settlement link only; it will not repair old complete links.

Public location pages belong to the docs. Browsing starts at `/docs/countries` and `/ru/docs/countries`; detail pages use `/docs/countries/{slug}`, `/docs/regions/{slug}`, and `/docs/cities/{slug}` with Russian equivalents. The former `/countries`, `/regions/{slug}`, and `/cities/{slug}` URLs redirect permanently with their query. Pages follow the hierarchy country → region → settlement → water source, with breadcrumbs from the docs overview. The directory lists every country with sources alphabetically in the page language under letter headings. A country page lists its regions, its settlements outside any region, the 24 settlements with the most sources, and the 10 visible sources with the most visible reports; it does not list the country's sources. A region page lists its settlements and its most reported sources. A settlement page explicitly describes water sources **near** the settlement: every source whose nearest settlement it is, nearest to its center first. Counts and listings include only visible, non-redirected sources. A source near a foreign settlement contributes to its actual country's count and the foreign settlement's nearest-source count independently. Empty and inactive place pages return 404; the empty directory is not indexed.

Lists with fewer than 5,000 entries (`geography.list_limit`) are shown in full; longer ones show 1,000 per page (`geography.page_size`) with `?page=N`, which stays in the canonical and alternate URLs, while invalid or out-of-range pages return 404. On pages without pagination, a `page` parameter or the former `settlements_page` one redirects permanently to the URL without it. Place lists with source counts and the most reported sources are cached for 10 minutes (`geography.cache_seconds`), so counts can lag that long. Uncached, the Russia page takes about half a second because its catalog has 195,000 settlements; other pages take well under that, and cached pages under 0.1 s. Canonical URLs, bilingual alternate links, and location sitemaps are included. Existing source detail pages link to these pages and read current catalog translations without requiring another geocoding pass solely for a name change.

## Map bounds

Countries and regions store a map viewport in `bbox_west`, `bbox_south`, `bbox_east`, and `bbox_north` (degrees), so place search can fit the map to them. West is in [-180, 180) and east is never less than west: a viewport crossing the antimeridian, such as Chukotka or Fiji, continues past 180 instead of wrapping.

Bounds come from the configured country and region GeoJSON, matched like the catalog import: countries by code, regions by GeoNames ID or else provider `source_key`. Natural Earth countries include remote territories, so the viewport covers the main cluster of polygon parts, not all of them. It starts from the largest part's outer ring and repeatedly adds parts of at least 0.5% of its area that lie within half the growing box's diagonal, and at least 2°, of that box. The floor is 5° when the largest part is under 1,000 km², so archipelagos of small islands such as the Austral Islands or Micronesia stay together. Smaller parts join only within a tenth of the largest part's diagonal (0.25°–3°) of that part itself, so nearby islands and exclaves such as the Florida Keys, Menorca, Hainan, and Kaliningrad count, while a chain of islets never carries the box further. This keeps Corsica but not French Guiana in France, Alaska but not Hawaii in the United States, and excludes Easter Island from Chile. By the same rule Svalbard joins Norway.

Countries without a polygon of their own, such as Svalbard and Jan Mayen or Bonaire, Saint Eustatius and Saba, use the polygons of active regions with their provider country code. Otherwise places use their active settlements: for countries, those with the provider country code, else catalog members; for regions, linked settlements, else provider settlements with the region's admin1 code (GeoNames England and Scotland). Settlements group like polygon parts with a 2° floor, starting from the most populous, but every settlement counts regardless of population and the most populous group wins. Outposts such as the Jan Mayen station or Da Nang's settlement in the Paracels therefore do not stretch the box, nor does one city filed under a distant region. Places with neither have null bounds. A single settlement gives a zero-size box, so map code should cap the zoom when fitting.

`geocoding:import-catalog` refreshes bounds after every successful import and reports how many came from polygons and settlements. Existing installations run the bounds import once; `--countries` and `--regions` choose local files:

```sh
php -d memory_limit=256M artisan geocoding:import-bounds
```

On the installed data this takes about 6 seconds: 242 countries use polygons (Svalbard and Jan Mayen, Bonaire, Saint Eustatius and Saba, and Tokelau through their regions), 7 use settlements, and Bouvet Island has no bounds; 4,577 regions use polygons, 633 use settlements, and 50 have no bounds, none of which has visible sources.

## Final Russia country-label policy

`RussiaBorderPolicy` wraps the base `ReverseGeocoder` and runs last for creation, coordinate edits, manual refresh, and every bulk mode. Any point contained in `GEOCODING_RUSSIA_BORDER_PATH` is linked to the `RU` country (`Russia` / `Россия`), even when the base geography or exact-coordinate export assigns a different country or has no coverage. The nearest settlement is retained; when the base has no city, the local provider can still look up the global nearest settlement. A policy feature's optional `region` takes precedence; foreign provider regions are not copied into Russia.

The bundled policy follows the Russian administrative claims listed in [Constitution article 65](https://www.minjust.gov.ru/ru/documents/8011/), including Crimea, Sevastopol, Donetsk, Luhansk, Zaporizhzhia, and Kherson. These are full administrative extents, without clipping to military control lines. It also includes the former Snihurivka district for the Aleksandrovsky and Snihurivka municipalities described in the [official Kherson documents](https://khogov.ru/documents/zakon-hersonskoj-oblasti-ot-27-12-2024-%E2%84%96-86-zho-o-statuse-i-granicza-municzipalnyh-obrazovanij-v-hersonskoj-oblasti/); all 58 settlements listed in the 2024 documents were checked against that historic polygon. The separate manifest documents the exact source scope, cross-checks, and limitations. Its polygons are derived geographic data, not an official surveyed boundary; the precision of the installed file determines containment near coasts and borders. Install a more precise approved GeoJSON when available.

The border layer includes adapted Natural Earth, Kartographia/OCHA [CC BY 3.0 IGO](https://creativecommons.org/licenses/by/3.0/igo/) administrative geometry, and an [OpenStreetMap](https://www.openstreetmap.org/copyright) historic district polygon under ODbL. The source snapshot and derived GeoJSON are distributed alongside their attribution manifest. Preserve attribution and applicable source licences when redistributing the layer; the general country and settlement datasets retain their separate licences. The public About page credits these providers and marks the geometry as adapted.

```sh
php artisan geocoding:update-russia-borders
php artisan geocoding:update-russia-borders --source=/path/to/updated-russia.geojson
php -d memory_limit=256M artisan geocoding:import-catalog
php artisan springs:geocode --queue
# Wait for the queued run to finish before auditing.
php artisan springs:audit-russia-borders
```

The border update command validates the complete replacement before atomic publication. `--output` chooses another policy path; `GEOCODING_RUSSIA_BORDER_PATH` configures the file actually used by the application. Updating the general country or settlement dataset does not replace this policy. Custom border files use a WGS84 GeoJSON `FeatureCollection` containing `Polygon`/`MultiPolygon` features, with optional `region` properties; all contained points receive the canonical Russian country labels regardless of feature country properties. Polygon holes are excluded and the outer boundary is included. Prefer split polygons at the antimeridian rather than rings that span it.

File contents are hashed, so replacements with unchanged size or timestamps are detected. A bounded bulk chunk and an audit use a consistent snapshot; the next chunk/request reloads changes. Missing or malformed enabled policy files fail validation, preventing conflicting base labels from being silently used. `GEOCODING_RUSSIA_BORDER_PATH=null` explicitly disables the policy; the audit then fails instead of reporting a false pass.

`springs:audit-russia-borders` is read-only and scans all stored source coordinates, including hidden and redirected records. It checks the country link and fails if the active Russia catalog entry is missing. It reports contained sources and mismatches, prints up to 20 mismatched source IDs, and exits nonzero on mismatches, invalid coordinates, disabled policy, or unreadable geometry. It uses only the border geometry and does not load the settlement catalog. Recalculate all sources before auditing an updated policy; `--missing` would skip complete but outdated links.

The normalized-catalog backfill completed on 2026-10-02, processing all 1,382,804 local sources in about 33 minutes. One source encountered a catalog lock timeout and was successfully retried; its historical run failure counter remains recorded. A final catalog reimport and targeted refresh corrected 15 territory-region links. The final read-only audit checked all 1,382,804 sources: 46,526 inside the configured policy, zero country-label or Russia-link mismatches, and zero invalid coordinates. Database checks also found no source/region or city/region parent-country inconsistencies. Bundled-data regression tests verify the manifest checksum, feature count, all 58 documented municipality settlement centres, Sevastopol coverage, and outside-country control points. These counts describe that database snapshot; repeat the audit after subsequent updates.

The snapshot has 1,362,930 country links, 1,362,046 region links, and 1,362,819 nearest-settlement links; 19,824 coordinates remain unmatched by the installed country geometry and exact fallback. Recognized geography without a catalog identity is not stored: this includes uncoded special territories (50 sources) and original-export settlement labels without a GeoNames ID (161 sources), which keep null links. No name-based identity guesses are made.

## Replacement datasets

Change `GEOCODING_PATH` to a relative project path or absolute path to replace country geography without application-code changes. Use atomic replacement: write a temporary file, validate it, then rename it over the configured path. `GEOCODING_CITIES_PATH` selects settlements; `GEOCODING_ADMIN1_PATH` selects administrative names and identifiers; `GEOCODING_REGIONS_PATH` selects region polygons. Catalog country identifiers and Russian names use `GEOGRAPHY_COUNTRY_INFO_PATH` and `GEOGRAPHY_RUSSIAN_NAMES_PATH`. Update-command `--directory` options stage files without changing these configured paths. Setting `GEOCODING_REFERENCE_PATH=null` disables the original-export fallback.

A GeoJSON `FeatureCollection` may contain country `Polygon`/`MultiPolygon` features and city `Point` features, with WGS84 `[longitude, latitude]` positions. Embedded city points take precedence over the external GeoNames catalog, making a combined replacement self-contained. Polygon holes are respected. City-only GeoJSON is supported but infers country from the closest city's label, which can be inaccurate near borders.

```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "properties": {"country": "Example", "country_code": "AA"},
      "geometry": {"type": "Polygon", "coordinates": [[[0, 0], [2, 0], [2, 2], [0, 2], [0, 0]]]}
    },
    {
      "type": "Feature",
      "properties": {"city": "Capital", "country_code": "AA", "region": "Central"},
      "geometry": {"type": "Point", "coordinates": [1, 1]}
    }
  ]
}
```

Supported properties include `country`, `country_ru`, `country_code`, `region`, and `city`. Aliases such as `ADMIN`, `ISO_A2`, and city `name` are recognized. For a different schema, add `properties` mappings to `config/geocoding.php`, using a property name or ordered aliases for each field. Country and city features should use the same country-code system. Labels must fit the database: 255 characters for names and two or three ASCII letters for country codes. External settlements use standard GeoNames 19-column TSV; region codes use the standard admin1 TSV.

The original export is also supported as the lookup source, but it is an exact-coordinate cache rather than a geographic dataset. Matching uses coordinates rounded to six decimal places, never spring IDs. Catalog import separately expects normalized country/region FeatureCollections and GeoNames TSV inputs. Custom lookup formats retain compatible text results, while catalog links require recognized stable identities and suitable catalog inputs. Approximate reference copying (`GEOCODING_REFERENCE_MAX_DISTANCE_KM`) is disabled by default and cannot establish actual country boundaries or nearest cities.

Application workflows depend on `App\Contracts\ReverseGeocoder`, bound to `GeographyReverseGeocoder`. It resolves catalog identities and containing region after the base provider and `RussiaBorderPolicy` establish the final country. To substitute the base provider, change the provider injected into the policy in `AppServiceProvider`, retaining the policy and catalog enrichment. Providers may implement `SnapshotReverseGeocoder` to reuse one source snapshot inside a bounded batch and `NearestCityLocator` to resolve a settlement when the base country result has no coverage. Custom city points without a recognized stable identifier leave the link null; similarly named settlements are never guessed into a catalog association.

## Automatic and manual lookups

`SpringObserver` resolves labels before creation or an effective coordinate change. This covers user submissions, user location edits, JSON OSM node/way imports, and the legacy XML import. Unchanged coordinates and unrelated edits do not repeat the lookup. OSM updates that preserve a user's edited location also preserve its labels.

An unavailable or invalid dataset is logged and does not prevent adding or moving a source. Derived fields are cleared so labels from an old location are never retained. A failed manual lookup preserves the previous labels; a successful lookup without coverage clears them and reports no match. Admins and superadmins can use **Refresh country and city** in the existing source action menu. Both the UI and the invokable action enforce the admin gate.

## Bulk commands

```sh
php artisan springs:geocode                  # Recalculate all sources
php artisan springs:geocode --missing        # Country or city is null/empty
php artisan springs:geocode --reset          # Clear derived fields, then recalculate all
php artisan springs:geocode --missing --queue
php artisan springs:geocode --resume          # Continue a queued/interrupted active run
```

`--missing` and `--reset` are mutually exclusive. All modes include hidden and redirected sources. Reset validates the dataset before clearing only the derived geocoding fields. It clears through the highest source ID present when the run starts; new sources use the automatic hook. Each run records progress, its processing cursor, and matched/unmatched/failed counts in `geocoding_runs`. Per-source failures are counted while other sources continue. Results are saved only if coordinates still match the lookup and the worker still owns its run.

Only one bulk run may be active. Queued runs use `GEOCODING_QUEUE_CONNECTION` (default `database`) and `GEOCODING_QUEUE` (default `geocoding`), independently of the application's default queue connection. Run a worker for that connection and queue:

```sh
php -d memory_limit=256M artisan queue:work database --queue=geocoding --timeout=60 --memory=200
```

Jobs process bounded chunks, checkpoint every source, and call the same Artisan command. The default database queue retries reserved jobs after 90 seconds; worker timeout is 60 seconds. Other queue connections must have a reservation/retry interval exceeding the worker timeout. An interrupted worker can resume from its saved cursor after its lease expires. Repeated checkpoints are safe because reverse geocoding changes only derived fields. Use a process supervisor for continuously available workers in production.

Restart long-lived queue workers after deploying application code or configuration. Geographic data-file replacements are detected automatically by the next bounded chunk.

## Filament

**System → Reverse geocoding** (`/filament/geocoding`) provides missing, recalculate-all, and clear-and-recalculate actions. Clear-and-recalculate has a confirmation describing the affected fields. Actions invoke the CLI and queue its work. The page polls persisted progress every three seconds and displays totals, matches, unmatched sources, failures, and recent runs. Queued or interrupted runs can be resumed or stopped to release the reservation. Without a queue worker, the page shows **Queued** with a waiting message.
