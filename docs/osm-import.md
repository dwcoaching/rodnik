# OSM Daily Import Flow

Entry: Filament admin → Create OverpassBatch → [CreateOverpassBatch::afterCreate()](app/Filament/Resources/OverpassBatchResource/Pages/CreateOverpassBatch.php)

Not scheduled in [Kernel.php](app/Console/Kernel.php) — triggered manually.

## Status fields (on OverpassBatch)

| Field | States |
|---|---|
| `imports_status` | `not created` → `created` |
| `checks_status` | `not created` → `creating` → `created` |
| `fetch_status` | `not started` → `fetching` → `fetched` (at 100% coverage) |
| `parse_status` | `not started` → `parsing` → `parsed` (at 100%) |
| `cleanup_status` | `started` → `completed` |
| `coverage`, `parsed_percentage` | 0–100 |

## Pipeline

```
afterCreate()
├─ $batch->createImports()                  [sync]
├─ CreateOverpassBatchChecks::dispatch      [queue: overpass]
└─ FetchOverpassBatchImports::dispatch      [queue: overpass]
        └─ on 100% coverage → ParseOverpassBatchImports::dispatch
                └─ on 100% parsed → CleanupOSMSprings + RemoveOlderOverpassArtifacts
```

All jobs use queue `overpass`, timeout `0`.

## Models

### [OverpassBatch](app/Models/OverpassBatch.php)
- `createImports()` — creates 360 OverpassImport records (1° longitude bands, full lat range)
- `createChecks()` — creates 64,800 OverpassCheck records (1×1° global grid; coverage tracking)
- `fetchImports()` — loops unfetched imports → `$import->fetch()` → `checkImports()` → `updateCoverage()` → `grindUpFailedImports()`
- `checkImports()` — sets `has_remarks` per fetched import
- `updateCoverage()` — links checks to covering imports via `covered_by`; at 100% → dispatches `ParseOverpassBatchImports`
- `grindUpFailedImports()` — calls `$import->grindUp()` on failures; recursively re-runs `fetchImports()`
- `parseImports()` — loops unparsed imports → `$import->parse()` → `updateParsedPercentage()`
- `updateParsedPercentage()` — at 100% → dispatches `CleanupOSMSprings` + `RemoveOlderOverpassArtifacts`

### [OverpassImport](app/Models/OverpassImport.php)
- `fetch()` — POST to Overpass API; stores JSON to `storage/app/overpass/responses/{id}.json`; sets `response_code`, `fetched_at`
- `responseHasRemarks()` — detects `remark` field in response (API error/warning)
- `parse()` — decodes JSON, calls `Overpass::parse($json)`, sets `parsed_at`
- `grindUp()` — subdivides failed import; sets `ground_up=true` on parent
  - `grindUpLongitudinally()` — splits lon range ÷10
  - `grindUpLatitudinally()` — adaptive step (60→20→10→5→1)
  - `retry1x1()` — last-resort same-bounds retry
- `getQueryAttribute()` — builds Overpass QL (13 node + 13 way water types, 180s timeout)

### [OverpassCheck](app/Models/OverpassCheck.php)
1×1° grid cell. Field `covered_by` → OverpassImport id.

## Jobs

| Job | Calls |
|---|---|
| [CreateOverpassBatchChecks](app/Jobs/CreateOverpassBatchChecks.php) | `$batch->createChecks()` |
| [FetchOverpassBatchImports](app/Jobs/FetchOverpassBatchImports.php) | `$batch->fetchImports()` |
| [ParseOverpassBatchImports](app/Jobs/ParseOverpassBatchImports.php) | `$batch->parseImports()` |
| [CleanupOSMSprings](app/Jobs/CleanupOSMSprings.php) | `$laundry->cleanup()`; sets `cleanup_status` |
| [RemoveOlderOverpassArtifacts](app/Jobs/RemoveOlderOverpassArtifacts.php) | `deleteWithArtifacts()` on all batches with `id < current` |

## Library

- [Overpass::parse($json)](app/Library/Overpass.php) — iterates nodes/ways; creates/updates Spring records; parses OSM tags; returns `{new, existing}`
- [Laundry::cleanup()](app/Library/Laundry.php) — chunks Springs matching false-positive tag combos (toilets, campsites, huts marked `drinking_water=no`) and `$spring->hide()`s them

## Manual/debug commands

Equivalents to the automated batch flow (not scheduled):
- `overpass:create-global` — [OverpassImportGlobalCreate](app/Console/Commands/OverpassImportGlobalCreate.php)
- `overpass:fetch-global` — [OverpassImportGlobalFetch](app/Console/Commands/OverpassImportGlobalFetch.php)
- `overpass:parse-global` — [OverpassImportGlobalParse](app/Console/Commands/OverpassImportGlobalParse.php)

## Key behaviors

- Fetch→Parse gated on **100% coverage** of 64,800 cells
- Failed/remarked imports are **subdivided and retried recursively**
- Parse triggers per-import; cleanup + artifact removal run **in parallel** at end
- Response JSONs persisted at `storage/app/overpass/responses/{id}.json`
