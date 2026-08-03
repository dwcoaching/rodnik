<?php

declare(strict_types=1);

test('report condition badge colors are fixed in the stylesheet', function () {
    $stylesheet = file_get_contents(__DIR__.'/../../resources/css/app.css');

    expect($stylesheet)
        ->toContain('border-color: #d3e9b7;')
        ->toContain('background-color: #d3e9b7;')
        ->toContain('border-color: #ffe4a8;')
        ->toContain('background-color: #ffe4a8;')
        ->toContain('border-color: #ffbfaf;')
        ->toContain('background-color: #febcb0;')
        ->toContain('color: #232323;')
        ->not->toContain('--report-tag-');
});

test('every warning badge uses the shared warning style', function () {
    $template = file_get_contents(__DIR__.'/../../resources/views/components/report-condition-badges.blade.php');

    expect($template)
        ->toContain("'report-condition-badge--warning' => \$condition->getColor() === 'warning'")
        ->toContain('class="report-condition-badge report-condition-badge--warning"')
        ->not->toContain('border-amber-200')
        ->not->toContain('bg-amber-50');
});
