<?php

use Illuminate\View\View;

use function Laravel\Folio\{name, render};

name('ru.docs.api');

render(fn (View $view) => view('pages.api'));

?>
