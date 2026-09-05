<?php

use App\Library\UserRanking;
use Illuminate\View\View;

use function Laravel\Folio\{name, render};

name('ru.docs.users');

render(fn (View $view): View => view('pages.users', ['users' => app(UserRanking::class)->paginate()]));

?>
