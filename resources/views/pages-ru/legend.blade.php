<?php

use Illuminate\View\View;

use function Laravel\Folio\{name, render};

name('ru.docs.legend');

render(fn (View $view) => view('pages.legend'));

?>
