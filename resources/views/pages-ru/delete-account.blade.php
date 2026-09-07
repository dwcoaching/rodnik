<?php

use Illuminate\View\View;

use function Laravel\Folio\{name, render};

name('ru.docs.delete-account');

render(fn (View $view) => view('pages.delete-account'));

?>
