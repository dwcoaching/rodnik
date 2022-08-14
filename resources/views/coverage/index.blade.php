<style>
    td {width: 5px; height: 5px;}
    .covered {background-color: #00cc00;}
    .missing {background-color: #000000;}
</style>

<table class="border" cellspacing="1" cellpadding="0" style="background-color: #000;">
    @foreach ($map as $rows)
        <tr>
            @foreach ($rows as $cell)
                @if ($cell->covered_by)
                    <td class="covered" title="{{ $cell->latitude_from }}, {{ $cell->longitude_from }}"></td>
                @else
                    <td class="missing" title="{{ $cell->latitude_from }}, {{ $cell->longitude_from }}"></td>
                @endif
            @endforeach
        </tr>
    @endforeach
</table>
