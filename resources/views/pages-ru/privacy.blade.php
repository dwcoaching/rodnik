<?php

use Illuminate\View\View;

use function Laravel\Folio\{name, render};

name('ru.docs.privacy');

render(fn (View $view) => view('pages.privacy'));

?>
