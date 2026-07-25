<?php

use Illuminate\View\View;

use function Laravel\Folio\{name, render};

name('ru.docs.about');

render(fn (View $view) => view('pages.about'));

?>
