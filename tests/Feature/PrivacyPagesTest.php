<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('privacy and deletion pages are public and keep the language in their URL', function (string $locale, string $prefix, string $otherPrefix, string $retention) {
    foreach (['privacy', 'delete-account'] as $page) {
        $this->withHeader('Accept-Language', $locale === 'en' ? 'ru' : 'en')
            ->get($prefix.'/docs/'.$page)
            ->assertSuccessful()
            ->assertSee('<html lang="'.$locale.'">', false)
            ->assertSee($retention)
            ->assertSee('CC0')
            ->assertSee('Telegram')
            ->assertSee('href="mailto:kolpakov@hey.com"', false)
            ->assertSee('<link rel="canonical" href="'.url($prefix.'/docs/'.$page).'">', false)
            ->assertSee('value="'.$otherPrefix.'/docs/'.$page.'"', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/docs/'.$page).'">', false)
            ->assertSee('<link rel="alternate" hreflang="ru" href="'.url('/ru/docs/'.$page).'">', false)
            ->assertDontSee('noindex, nofollow');
    }
})->with([
    'English' => ['en', '', '/ru', 'Backups rotate out within 30 days.'],
    'Russian' => ['ru', '/ru', '', 'Резервные копии исчезают при ротации в течение 30 дней.'],
]);

test('privacy pages disclose public licensing and the services which receive data', function (string $prefix, string $accountText, string $retentionText) {
    $this->get($prefix.'/docs/privacy')
        ->assertSuccessful()
        ->assertSee($accountText)
        ->assertSee($retentionText)
        ->assertSee('OpenStreetMap')
        ->assertSee('ODbL')
        ->assertSee('UI Avatars')
        ->assertSee('Strava')
        ->assertSee('href="https://creativecommons.org/publicdomain/zero/1.0/"', false)
        ->assertSee('href="'.url($prefix.'/docs/delete-account').'"', false);
})->with([
    'English' => ['', 'we do not require a real name or verify email addresses', 'Reports, photos and edits remain public indefinitely'],
    'Russian' => ['/ru', 'настоящее имя не требуется, email не проверяется', 'Отчёты, фото и правки остаются общедоступными бессрочно'],
]);

test('deletion instructions point to authenticated settings instead of email requests', function (string $prefix, string $instructions) {
    $this->get($prefix.'/docs/delete-account')
        ->assertSuccessful()
        ->assertSee($instructions)
        ->assertSee('href="'.route('profile.show').'#delete-account"', false)
        ->assertSee('href="'.url($prefix.'/docs/privacy').'"', false);
})->with([
    'English' => ['', 'we do not process account-deletion requests by email or Telegram'],
    'Russian' => ['/ru', 'по email и через Telegram аккаунты не удаляются'],
]);

test('documentation navigation groups matching policy links under Legal with emojis', function (string $locale, string $prefix, string $legalTitle) {
    $this->get($prefix.'/docs/about')
        ->assertSuccessful()
        ->assertSeeInOrder([
            $legalTitle,
            '🔒',
            __('privacy.title', [], $locale),
            '🗑️',
            __('privacy.deletion.title', [], $locale),
        ])
        ->assertSee('href="'.url($prefix.'/docs/privacy').'"', false)
        ->assertSee('href="'.url($prefix.'/docs/delete-account').'"', false);
})->with([
    'English' => ['en', '', 'Legal'],
    'Russian' => ['ru', '/ru', 'Правовая информация'],
]);

test('the homepage and registration keep policy links in documentation', function (string $locale, string $prefix) {
    $this->get($prefix ?: '/')
        ->assertSuccessful()
        ->assertDontSee('href="'.url('/docs/privacy').'"', false)
        ->assertDontSee('href="'.url('/docs/delete-account').'"', false)
        ->assertDontSee('href="'.url('/ru/docs/privacy').'"', false)
        ->assertDontSee('href="'.url('/ru/docs/delete-account').'"', false);

    $this->withHeader('X-Rodnik-Locale', $locale)
        ->get('/register')
        ->assertSuccessful()
        ->assertSee(__('privacy.registration_hint', [], $locale))
        ->assertDontSee('href="'.url('/docs/privacy').'"', false)
        ->assertDontSee('href="'.url('/docs/delete-account').'"', false)
        ->assertDontSee('href="'.url('/ru/docs/privacy').'"', false)
        ->assertDontSee('href="'.url('/ru/docs/delete-account').'"', false);
})->with([
    'English' => ['en', ''],
    'Russian' => ['ru', '/ru'],
]);

test('account settings retain deletion controls and explanations without policy links', function (string $locale) {
    $user = User::factory()->create(['locale' => $locale]);

    $this->actingAs($user)
        ->get(route('profile.show'))
        ->assertSuccessful()
        ->assertDontSee('href="'.url('/docs/privacy').'"', false)
        ->assertDontSee('href="'.url('/docs/delete-account').'"', false)
        ->assertDontSee('href="'.url('/ru/docs/privacy').'"', false)
        ->assertDontSee('href="'.url('/ru/docs/delete-account').'"', false)
        ->assertSee('id="delete-account"', false)
        ->assertSee('wire:click="confirmUserDeletion"', false)
        ->assertSee('wire:click="deleteUser"', false)
        ->assertSee(__('privacy.deletion.summary', [], $locale))
        ->assertSee(__('privacy.deletion.confirmation', [], $locale))
        ->assertDontSee('all of its resources and data will be permanently deleted');
})->with([
    'English' => ['en'],
    'Russian' => ['ru'],
]);

test('opening deletion settings as a guest requires sign in', function () {
    $this->get(route('profile.show'))
        ->assertRedirect(route('login'));
});
