<?php

use App\Support\Docs\PublicMaps;
use Illuminate\View\View;

use function Laravel\Folio\{name, render};

name('ru.docs.maps');

render(fn (PublicMaps $maps): View => view('pages.maps', ['maps' => $maps->paginate()]));

?>
