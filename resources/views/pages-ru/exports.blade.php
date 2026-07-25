<?php

use Illuminate\View\View;

use function Laravel\Folio\{name, render};

name('ru.docs.exports');

render(fn (View $view) => view('pages.exports'));

?>
