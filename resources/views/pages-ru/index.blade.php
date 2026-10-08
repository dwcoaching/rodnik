<?php

use App\Support\Docs\DocsOverview;
use Illuminate\View\View;

use function Laravel\Folio\{name, render};

name('ru.docs.index');

render(fn (DocsOverview $overview): View => view('pages.index', $overview->data()));

?>
