<?php

use Illuminate\View\View;

use function Laravel\Folio\{name, render};

name('ru.docs.coll-de-sa-batalla');

render(fn (View $view) => view('pages.coll-de-sa-batalla'));

?>
