<?php

declare(strict_types=1);

namespace App\Services\Geocoding;

/** Stable source identities for regions in the configured territorial policy. */
final class ClaimedRegion
{
    private const REGIONS = [
        703883 => ['11', 'UA-43', 'Republic of Crimea', 'Республика Крым', ['UA01', 'UA-43']],
        694422 => ['20', 'UA-40', 'Sevastopol', 'Севастополь', ['UA85', 'UA-40']],
        709716 => ['05', 'UA-14', 'Donetsk People’s Republic', 'Донецкая Народная Республика', ['UA14', 'UA-14']],
        702657 => ['14', 'UA-09', 'Luhansk People’s Republic', 'Луганская Народная Республика', ['UA44', 'UA-09']],
        706442 => ['08', 'UA-65', 'Kherson Oblast', 'Херсонская область', ['UA65', 'UA-65', 'OSM-R1738982']],
        687699 => ['26', 'UA-23', 'Zaporizhzhia Oblast', 'Запорожская область', ['UA23', 'UA-23']],
    ];

    public static function fromGeonamesId(int $id): ?array
    {
        $region = self::REGIONS[$id] ?? null;

        return $region === null ? null : [
            'geonames_id' => $id,
            'source_key' => 'geonames-admin1:UA.'.$region[0],
            'country_code' => 'RU',
            'source_country_code' => 'UA',
            'admin1_code' => $region[0],
            'iso_code' => $region[1],
            'name' => $region[2],
            'name_ru' => $region[3],
        ];
    }

    public static function fromCode(?string $code): ?array
    {
        foreach (self::REGIONS as $id => $region) {
            if (in_array($code, $region[4], true)) {
                return self::fromGeonamesId($id);
            }
        }

        return null;
    }

    public static function fromName(?string $name): ?array
    {
        foreach (self::REGIONS as $id => $region) {
            if ($name === $region[3] || $name === $region[2]) {
                return self::fromGeonamesId($id);
            }
        }

        return null;
    }
}
