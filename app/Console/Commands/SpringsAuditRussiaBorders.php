<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Geocoding\RussiaBorderPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SpringsAuditRussiaBorders extends Command
{
    protected $signature = 'springs:audit-russia-borders';

    protected $description = 'Check that sources within the configured Russia border policy link to Russia';

    public function handle(RussiaBorderPolicy $policy): int
    {
        if (config('geocoding.russia_border_path') === null || config('geocoding.russia_border_path') === '') {
            $this->error('Russia border policy is disabled. Configure GEOCODING_RUSSIA_BORDER_PATH before auditing.');

            return self::FAILURE;
        }

        $checked = 0;
        $inside = 0;
        $mismatched = 0;
        $invalid = 0;
        $examples = [];

        try {
            $russiaId = DB::table('countries')->where('code', 'RU')->where('is_active', true)->value('id');

            if ($russiaId === null) {
                $this->error('Russia is missing from the geography catalog. Run geocoding:import-catalog before auditing.');

                return self::FAILURE;
            }

            $policy->withBorderSnapshot(function () use ($policy, $russiaId, &$checked, &$inside, &$mismatched, &$invalid, &$examples): void {
                DB::table('springs')->select(['id', 'latitude', 'longitude', 'country_id'])
                    ->chunkById(2000, function ($sources) use ($policy, $russiaId, &$checked, &$inside, &$mismatched, &$invalid, &$examples): void {
                        foreach ($sources as $source) {
                            if (! is_numeric($source->latitude) || ! is_numeric($source->longitude)
                                || ! is_finite((float) $source->latitude) || ! is_finite((float) $source->longitude)
                                || abs((float) $source->latitude) > 90 || abs((float) $source->longitude) > 180) {
                                $invalid++;

                                continue;
                            }

                            $checked++;

                            if (! $policy->contains((float) $source->latitude, (float) $source->longitude)) {
                                continue;
                            }

                            $inside++;

                            if ((int) $source->country_id === (int) $russiaId) {
                                continue;
                            }

                            $mismatched++;

                            if (count($examples) < 20) {
                                $examples[] = (string) $source->id;
                            }
                        }
                    });
            });
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line("Checked {$checked} water sources; {$inside} within the Russia border policy; {$mismatched} mismatched countries; {$invalid} invalid coordinates.");

        if ($mismatched > 0) {
            $this->error('Mismatched source IDs (up to 20): '.implode(', ', $examples).'.');
            $this->line('Run springs:geocode to recalculate saved links, then repeat this audit.');

            return self::FAILURE;
        }

        if ($invalid > 0) {
            $this->error('Invalid coordinates must be corrected before the audit can pass.');

            return self::FAILURE;
        }

        $this->info('All sources within the configured border policy link to Russia.');

        return self::SUCCESS;
    }
}
