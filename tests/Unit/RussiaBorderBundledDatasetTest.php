<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\ReverseGeocoder;
use App\Services\Geocoding\GeocodedLocation;
use App\Services\Geocoding\GeoJsonFeatureStream;
use App\Services\Geocoding\RussiaBorderPolicy;
use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;

final class RussiaBorderBundledDatasetTest extends TestCase
{
    public function test_manifest_matches_the_bundled_border_file(): void
    {
        $manifest = $this->manifest();
        $path = $this->directory().'/'.$manifest['output']['file'];

        self::assertSame(hash_file('sha256', $path), $manifest['output']['sha256']);
        self::assertSame(filesize($path), $manifest['output']['bytes']);
        self::assertSame(iterator_count(GeoJsonFeatureStream::features($path)), $manifest['counts']['features']);
    }

    public function test_bundled_policy_covers_all_documented_settlements_and_keeps_outside_cities_unchanged(): void
    {
        $manifest = $this->manifest();
        $settlements = $manifest['counts']['municipalities']['settlements'];
        self::assertCount(58, $settlements);
        self::assertSame(58, $manifest['counts']['municipalities']['verified_settlements']);

        $base = new class implements ReverseGeocoder
        {
            public function validateSource(): void {}

            public function reverse(float $latitude, float $longitude): ?GeocodedLocation
            {
                return new GeocodedLocation('Ukraine', 'UA', 'Provider settlement', 'Украина', 'Provider region');
            }
        };
        $policy = new RussiaBorderPolicy($base, new Repository(['geocoding' => [
            'russia_border_path' => $this->directory().'/'.$manifest['output']['file'],
        ]]));

        // This uses only the bundled border layer and a stub country provider.
        // No settlement catalog, database, or writable compiled cache is needed.
        $policy->withBorderSnapshot(function () use ($policy, $settlements): void {
            foreach ($settlements as $code => $settlement) {
                [$longitude, $latitude] = $settlement['coordinates'];
                $message = $code.' '.$settlement['name_uk'];
                self::assertTrue($policy->contains($latitude, $longitude), $message);
                $location = $policy->reverse($latitude, $longitude);
                self::assertSame(['Russia', 'Россия', 'RU'], [$location?->country, $location?->countryRu, $location?->countryCode], $message);
            }

            foreach ([
                'Sevastopol city centre' => [44.61665, 33.52537],
                'Nakhimov Square' => [44.616111, 33.524444],
            ] as $name => [$latitude, $longitude]) {
                self::assertTrue($policy->contains($latitude, $longitude), $name);
                $location = $policy->reverse($latitude, $longitude);
                self::assertSame(['Russia', 'Россия', 'RU'], [$location?->country, $location?->countryRu, $location?->countryCode], $name);
            }

            foreach ([
                'Kyiv' => [50.4501, 30.5234],
                'Mykolaiv' => [46.975, 31.995],
            ] as $name => [$latitude, $longitude]) {
                self::assertFalse($policy->contains($latitude, $longitude), $name);
                $location = $policy->reverse($latitude, $longitude);
                self::assertSame(['Ukraine', 'Украина', 'UA'], [$location?->country, $location?->countryRu, $location?->countryCode], $name);
            }
        });
    }

    private function manifest(): array
    {
        return json_decode(file_get_contents($this->directory().'/russia-claimed-borders.manifest.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    private function directory(): string
    {
        return dirname(__DIR__, 2).'/resources/geocoding';
    }
}
