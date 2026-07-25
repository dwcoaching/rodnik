<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('ui.moscow_stats.title') }}</title>
        <style>
            table { border-collapse: collapse; }
            th { text-align: left; }
            .value { text-align: right; }
            td, th { padding: 4px 8px; }
        </style>
    </head>
    <body>
        @foreach ($resultSet as $areaName => $stats)
            <h1>{{ $areaName }}</h1>
            <table border>
                <tr>
                    <th>{{ __('ui.moscow_stats.type') }}</th>
                    <th>{{ __('ui.moscow_stats.total') }}</th>
                    <th>{{ __('ui.moscow_stats.explored') }}</th>
                    <th>{{ __('ui.moscow_stats.percentage') }}</th>
                </tr>
                @foreach ($stats as $type => $discoveredness)
                    <tr>
                        <td>{{ $type }}</td>
                        <td class="value">{{ $discoveredness->get('unknown', 0) + $discoveredness->get('visited', 0) }}</td>
                        <td class="value">{{ $discoveredness->get('visited', 0) }}</td>
                        <td class="value">
                            @if ($discoveredness->get('visited', 0) + $discoveredness->get('unknown', 0) > 0 )
                                {{
                                    number_format(
                                        round(
                                            $discoveredness->get('visited', 0)
                                            / ( $discoveredness->get('visited', 0) + $discoveredness->get('unknown', 0) )
                                            * 100,
                                            1
                                        ), 1, ','
                                    )
                                }}%
                            @else
                                {{ __('ui.moscow_stats.not_available') }}
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr>
                    <th>{{ __('ui.moscow_stats.grand_total') }}</th>
                    <th class="value">
                        {{
                            $stats->sum(function ($group) {
                                return $group->sum();
                            })
                        }}
                    </th>
                    <th class="value">
                        {{
                            $stats->sum(function ($group) {
                                return $group->get('visited', 0);
                            })
                        }}
                    </th>
                    <th class="value">
                        @if ($discoveredness->get('visited', 0) + $discoveredness->get('unknown', 0) > 0 )
                            {{
                                number_format(
                                    round(
                                        $stats->sum(function ($group) {
                                            return $group->get('visited', 0);
                                        })
                                        /
                                        $stats->sum(function ($group) {
                                            return $group->sum();
                                        })
                                        * 100,
                                        1
                                    ), 1, ','
                                )
                            }}%
                        @else
                            {{ __('ui.moscow_stats.not_available') }}
                        @endif
                    </th>
                </tr>
            </table>
        @endforeach
    </body>
</html>
