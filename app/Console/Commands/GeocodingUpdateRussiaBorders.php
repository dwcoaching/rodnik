<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Geocoding\RussiaBorderDatasetBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

final class GeocodingUpdateRussiaBorders extends Command
{
    public const COUNTRIES_URL = 'https://raw.githubusercontent.com/nvkelso/natural-earth-vector/v5.1.2/geojson/ne_10m_admin_0_countries.geojson';

    public const ADMIN1_URL = 'https://raw.githubusercontent.com/nvkelso/natural-earth-vector/v5.1.2/geojson/ne_10m_admin_1_states_provinces.geojson';

    public const UKRAINE_URL = 'https://data.humdata.org/dataset/d23f529f-31e4-4021-a65b-13987e5cfb42/resource/681beb86-391b-4a08-8140-ca52e80fcdce/download/ukr_admin_boundaries.geojson.zip';

    public const MUNICIPALITIES_URL = 'https://nominatim.openstreetmap.org/lookup?osm_ids=R1738982&format=geojson&polygon_geojson=1';

    protected $signature = 'geocoding:update-russia-borders
        {--output= : Destination GeoJSON (defaults to the configured Russian border path)}
        {--source= : Normalize a local replacement policy GeoJSON instead of deriving from upstream boundaries}
        {--countries-source= : Local Natural Earth country GeoJSON instead of downloading}
        {--admin1-source= : Local Natural Earth administrative GeoJSON instead of downloading}
        {--ukraine-source= : Local OCHA COD-AB Ukraine GeoJSON ZIP instead of downloading}
        {--municipalities-source= : Local historic Snihurivka-raion OSM FeatureCollection instead of the bundled snapshot}
        {--refresh-municipalities : Refresh the one known OSM relation through Nominatim and cache the result}';

    protected $description = 'Build a replaceable Russian claimed-border policy from pinned administrative geography';

    public function handle(RussiaBorderDatasetBuilder $builder): int
    {
        $output = $this->option('output') ?: config('geocoding.russia_border_path') ?: resource_path('geocoding/russia-claimed-borders.geojson');

        if (! str_starts_with($output, DIRECTORY_SEPARATOR)) {
            $output = base_path($output);
        }

        $directory = dirname($output);
        File::ensureDirectoryExists($directory);
        File::ensureDirectoryExists(storage_path('app'));
        $lock = @fopen(storage_path('app/russia-border-update-').hash('sha256', $output).'.lock', 'c');

        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            $this->error('Another command is updating this border policy, or its lock is unavailable.');

            return self::FAILURE;
        }

        $temporary = $directory.'/.russia-borders-'.Str::uuid();
        File::ensureDirectoryExists($temporary);

        try {
            $localPolicy = $this->option('source');
            if ($localPolicy && ($this->option('countries-source') || $this->option('admin1-source') || $this->option('ukraine-source') || $this->option('municipalities-source') || $this->option('refresh-municipalities'))) {
                throw new RuntimeException('--source cannot be combined with individual upstream source options.');
            }

            $sources = [
                'countries' => ['url' => self::COUNTRIES_URL, 'option' => 'countries-source'],
                'admin1' => ['url' => self::ADMIN1_URL, 'option' => 'admin1-source'],
                'ukraine' => ['url' => self::UKRAINE_URL, 'option' => 'ukraine-source'],
            ];
            $inputs = [];

            foreach ($localPolicy ? [] : $sources as $name => $source) {
                $path = $temporary.'/'.$name.($name === 'ukraine' ? '.zip' : '.geojson');

                if ($local = $this->option($source['option'])) {
                    $local = str_starts_with($local, DIRECTORY_SEPARATOR) ? $local : base_path($local);

                    if (! @copy($local, $path)) {
                        throw new RuntimeException("Could not copy local {$name} border input.");
                    }
                } else {
                    $this->line('Downloading '.$source['url']);
                    Http::connectTimeout(15)->timeout(180)->retry(3, 1000)
                        ->withOptions(['sink' => $path])->get($source['url'])->throw();
                }

                $inputs[$name] = ['url' => $source['url'], 'sha256' => hash_file('sha256', $path), 'bytes' => filesize($path), 'input' => $local ? 'local' : 'download'];
            }

            if (! $localPolicy) {
                $archive = new ZipArchive;
                if ($archive->open($temporary.'/ukraine.zip') !== true) {
                    throw new RuntimeException('Could not open the OCHA Ukraine GeoJSON archive.');
                }
                try {
                    foreach (['ukr_admin1.geojson', 'ukr_adminpoints.geojson'] as $member) {
                        if ($archive->locateName($member) === false || ! $archive->extractTo($temporary, $member)) {
                            throw new RuntimeException('The OCHA Ukraine archive is missing a required administrative layer.');
                        }
                    }
                } finally {
                    $archive->close();
                }
                $municipalities = $this->option('municipalities-source');
                $cache = storage_path('app/geocoding-border-inputs/snihurivka-raion.geojson');
                if ($municipalities && $this->option('refresh-municipalities')) {
                    throw new RuntimeException('--municipalities-source cannot be combined with --refresh-municipalities.');
                }
                if ($this->option('refresh-municipalities')) {
                    $this->line('Refreshing the known historical OSM relation (Nominatim usage policy: https://operations.osmfoundation.org/policies/nominatim/).');
                    Http::withUserAgent('Rodnik border-dataset updater (https://rodnik.today)')
                        ->connectTimeout(15)->timeout(60)->retry(2, 1500)
                        ->withOptions(['sink' => $temporary.'/municipalities.geojson'])->get(self::MUNICIPALITIES_URL)->throw();
                    $input = 'download';
                } else {
                    $municipalities = $municipalities ?: (is_file($cache) ? $cache : resource_path('geocoding/russia-claimed-municipalities-osm.geojson'));
                    $municipalities = str_starts_with($municipalities, DIRECTORY_SEPARATOR) ? $municipalities : base_path($municipalities);
                    if (! @copy($municipalities, $temporary.'/municipalities.geojson')) {
                        throw new RuntimeException('Could not copy the historical municipal boundary snapshot.');
                    }
                    $input = 'local';
                }
                $inputs['municipalities'] = ['url' => self::MUNICIPALITIES_URL, 'sha256' => hash_file('sha256', $temporary.'/municipalities.geojson'), 'bytes' => filesize($temporary.'/municipalities.geojson'), 'input' => $input, 'osm_relation_id' => 1738982];
            }

            if ($localPolicy) {
                $localPolicy = str_starts_with($localPolicy, DIRECTORY_SEPARATOR) ? $localPolicy : base_path($localPolicy);
                $inputs['local_policy'] = ['file' => basename($localPolicy), 'sha256' => hash_file('sha256', $localPolicy), 'bytes' => filesize($localPolicy), 'input' => 'local'];
                $counts = $builder->normalizePolicy($localPolicy, $temporary.'/borders.geojson');
            } else {
                $counts = $builder->build($temporary.'/countries.geojson', $temporary.'/admin1.geojson', $temporary.'/borders.geojson', $temporary.'/ukr_admin1.geojson', $temporary.'/municipalities.geojson', $temporary.'/ukr_adminpoints.geojson');
            }
            $manifest = [
                'generated_at' => now()->utc()->toIso8601String(),
                'policy' => 'russia-claimed-administrative-extent',
                'official_geometry' => false,
                'provider' => $localPolicy ? 'Local replacement policy' : 'Natural Earth; Kartographia / OCHA Ukraine; OpenStreetMap contributors',
                'version' => $localPolicy ? null : 'Natural Earth 5.1.2; OCHA COD-AB Ukraine v05; historical OSM relation 1738982 snapshot',
                'license' => $localPolicy ? 'Source-specific; this updater does not establish the input licence' : 'Per-feature licences retained: Natural Earth public domain; OCHA CC BY 3.0 IGO; OSM-derived historical-raion geometry ODbL 1.0.',
                'attribution' => $localPolicy ? [] : [
                    ['provider' => 'Natural Earth', 'source' => 'https://www.naturalearthdata.com/', 'license' => 'Public domain', 'license_url' => 'https://www.naturalearthdata.com/about/terms-of-use/'],
                    ['provider' => 'State Scientific Production Enterprise Kartographia / OCHA Ukraine', 'source' => 'https://data.humdata.org/dataset/cod-ab-ukr', 'license' => 'CC BY 3.0 IGO', 'license_url' => 'https://creativecommons.org/licenses/by/3.0/igo/', 'valid_on' => '2025-09-01', 'version' => 'v05', 'modifications' => 'Select complete claimed subject polygons and replace country/region labels for the application policy; use settlement centres for coverage verification.'],
                    ['provider' => 'OpenStreetMap contributors', 'source' => 'https://www.openstreetmap.org/relation/1738982', 'license' => 'ODbL 1.0', 'license_url' => 'https://opendatacommons.org/licenses/odbl/1-0/', 'snapshot_file' => 'russia-claimed-municipalities-osm.geojson', 'modifications' => 'Retain historical raion polygon; attach Russia and Kherson policy labels. OSM-derived geometry remains available under ODbL 1.0.'],
                ],
                'policy_sources' => [
                    'https://www.minjust.gov.ru/ru/documents/8011/',
                    'https://publication.pravo.gov.ru/Document/View/0001201403210014',
                    'https://publication.pravo.gov.ru/Document/View/0001202210050005',
                    'https://publication.pravo.gov.ru/Document/View/0001202210050006',
                    'https://publication.pravo.gov.ru/Document/View/0001202210050007',
                    'https://publication.pravo.gov.ru/Document/View/0001202210050008',
                    'https://khogov.ru/o-hersonskoj-oblasti/',
                    'https://khogov.ru/wp-content/uploads/2024/12/85-zho-1.pdf',
                    'https://khogov.ru/wp-content/uploads/2024/12/86-zho-3.pdf',
                    'https://khogov.ru/wp-content/uploads/2024/02/27-ukaz-%E2%84%9658-u-ot-29.03.2023.pdf',
                    'https://khogov.ru/wp-content/uploads/2024/02/47-ukaz-%E2%84%9677-u-ot-29.03.2023.pdf',
                ],
                'inputs' => $inputs,
                'official_reference_documents' => $localPolicy ? [] : [
                    ['url' => 'https://khogov.ru/wp-content/uploads/2024/12/85-zho-1.pdf', 'date' => '2024-12-27', 'sha256' => 'cff203da24fe1406516af01f29de010c6543878d616727284dac4453ff5a20cd', 'scope' => 'Law 85-ZHO Appendix 1: Aleksandrovsky 19 and Snihurivka 39 settlement names and aliases.'],
                    ['url' => 'https://khogov.ru/wp-content/uploads/2024/12/86-zho-3.pdf', 'date' => '2024-12-27', 'sha256' => 'bf6234341a0883dc3b11415f8afffbf8789fc0aea40591bd1a8b9a0c6f73285f', 'scope' => 'Law 86-ZHO Articles 5 and 23: the same municipal settlement lists.'],
                    ['url' => 'https://khogov.ru/wp-content/uploads/2024/02/27-ukaz-%E2%84%9658-u-ot-29.03.2023.pdf', 'date' => '2023-03-29', 'sha256' => 'cfeb72b6de201546af88c841274a1c75c3ffa17fc68522b01c75702d457596fb', 'scope' => 'Decree 58-u defines external settlement territory boundaries for Aleksandrovsky, without surveyed coordinate geometry.'],
                    ['url' => 'https://khogov.ru/wp-content/uploads/2024/02/47-ukaz-%E2%84%9677-u-ot-29.03.2023.pdf', 'date' => '2023-03-29', 'sha256' => 'f8d188e95e3b253c6affcf2a5dda2bdd5f25388162c00ddc6ade6f672af863ae', 'scope' => 'Decree 77-u older Snihurivka list includes Bezimenne.'],
                ],
                'output' => ['file' => basename($output), 'sha256' => hash_file('sha256', $temporary.'/borders.geojson'), 'bytes' => filesize($temporary.'/borders.geojson')],
                'counts' => $counts,
                'transformations' => $localPolicy ? ['Validate every polygon; preserve coordinates and optional region labels; enforce canonical country Russia / Россия / RU.'] : [
                    'Retain Russian country geography, including islands, from Natural Earth.',
                    'Prefer detailed OCHA / Kartographia complete administrative polygons for Crimea, Sevastopol, Donetsk, Luhansk, Kherson and Zaporizhzhia; retain Natural Earth administrative fallback coverage and do not clip to military control lines.',
                    'Add the historical Snihurivka-raion OSM polygon as the combined external extent of the additional Aleksandrovsky and Snihurivka municipal claims described by the official Kherson governor history.',
                    'Verify all 58 settlements listed by Kherson laws 85-ZHO and 86-ZHO (27 December 2024) against OCHA COD-AB v05 settlement centres: 19 Aleksandrovsky and 39 Snihurivka. Current hromadas alone are insufficient because ten listed places now belong to Zavodska or Shevchenkivska.',
                    'Assign canonical country Russia / Россия / RU to every feature and use Russian subject names for the six selected administrative regions.',
                ],
                'limitations' => $localPolicy ? ['Policy extent and original source licence are supplied by the operator; source hash records the exact input used.'] : [
                    'This is derived generalized administrative geography, not an official Russian surveyed border or a certification of legal compliance.',
                    'The additional municipalities use a historical administrative polygon anchored to official subject/settlement descriptions, not a newly surveyed Russian border. All 58 current listed settlement centres are covered. The historical polygon also contains Bezimenne, present in the 2023 Snihurivka decree but absent from the 2024 settlement lists; no unsupported cut around its territory is inferred.',
                    'Maritime zones and claims to continental-shelf rights are not land-country polygons.',
                ],
            ];
            $json = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";

            if (file_put_contents($temporary.'/manifest.json', $json) !== mb_strlen($json, '8bit')) {
                throw new RuntimeException('Could not write the Russian border attribution manifest.');
            }

            $manifestPath = preg_replace('/\.geojson$/i', '', $output).'.manifest.json';

            if (! rename($temporary.'/borders.geojson', $output) || ! rename($temporary.'/manifest.json', $manifestPath)) {
                throw new RuntimeException('Could not publish the Russian border policy files.');
            }
            if (! $localPolicy && $this->option('refresh-municipalities')) {
                File::ensureDirectoryExists(dirname($cache));
                if (! rename($temporary.'/municipalities.geojson', $cache)) {
                    throw new RuntimeException('Border policy was installed but its refreshed municipal snapshot could not be cached.');
                }
            }

            $this->info('Installed '.$counts['features'].' polygon features in '.$output.'.');
            $this->line('Policy scope and source attribution: '.$manifestPath);
            $this->line('Run springs:geocode to update saved country labels.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($temporary);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
