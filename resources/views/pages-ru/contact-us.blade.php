<?php

use Illuminate\View\View;

use function Laravel\Folio\{name, render};

name('ru.docs.contact-us');

render(fn (View $view) => view('pages.contact-us'));

?>
