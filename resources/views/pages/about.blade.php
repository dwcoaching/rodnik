<?php

use function Laravel\Folio\name;

name('docs.about');

?>

@extends('folio.index')

@section('title', __('pages.about.title').' — Rodnik.today')
@section('description', __('pages.about.seo_description'))

@section('content')
    <div class="prose">
        <div class="font-black text-2xl">
            {{ __('pages.about.hero') }}
        </div>
        <div class="mt-3 max-w-prose">
            {{ __('pages.about.introduction') }}
        </div>
        <div class="mt-3">
            {{ __('pages.about.openstreetmap_feedback') }}
        </div>
        <div class="mt-9 font-black text-xl">
            {{ __('pages.about.copyright.title') }}
        </div>
        <div class="mt-3">
            {{ __('pages.about.copyright.before_license') }}
            <a href="https://www.openstreetmap.org/copyright" class="text-blue-600" target="_blank">ODbL</a>.
            {{ __('pages.about.copyright.after_license') }}
        </div>

        <div class="mt-9 font-black text-xl">
            {{ __('pages.about.authors.title') }}
        </div>
        <div class="mt-3">
            {{ __('pages.about.authors.description') }} <b>{{ __('pages.about.authors.invitation') }}</b>
        </div>

        <div class="mt-9 font-black text-xl">
            {{ __('pages.about.more_information.title') }}
        </div>
        <ul class="mt-3">
            <li class="list-disc">
                <a href="https://docs.google.com/document/d/173TpVT7EQCEVaLyL3uB9dsjSwYiSzZP-jeKSuRPVwfM/edit" class="text-blue-600">{{ __('pages.about.more_information.learn_more') }}</a> <i>({{ __('pages.about.more_information.google_document') }})</i>.
            </li>
            <li>
                <a href="https://docs.google.com/spreadsheets/d/1sDnIOWgyEtAAMGeFpSk0KXX2Qfu8A3PpZNFN_MxcWN0/edit?gid=0#gid=0" class="text-blue-600" target="_blank">
                    {{ __('pages.about.more_information.taxonomy') }}
                </a> ({{ __('pages.about.more_information.google_spreadsheet') }})
            </li>
        </ul>

        <div class="mt-9 font-black text-xl">
            {{ __('pages.about.keep_in_touch') }}
        </div>

        <div class="mt-3">
            <a href="https://t.me/rodnik_today" target="_blank" class="block font-normal text-blue-600 hover:text-blue-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="inline mr-1" viewBox="0 0 16 16">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8.287 5.906c-.778.324-2.334.994-4.666 2.01-.378.15-.577.298-.595.442-.03.243.275.339.69.47l.175.055c.408.133.958.288 1.243.294.26.006.549-.1.868-.32 2.179-1.471 3.304-2.214 3.374-2.23.05-.012.12-.026.166.016.047.041.042.12.037.141-.03.129-1.227 1.241-1.846 1.817-.193.18-.33.307-.358.336a8.154 8.154 0 0 1-.188.186c-.38.366-.664.64.015 1.088.327.216.589.393.85.571.284.194.568.387.936.629.093.06.183.125.27.187.331.236.63.448.997.414.214-.02.435-.22.547-.82.265-1.417.786-4.486.906-5.751a1.426 1.426 0 0 0-.013-.315.337.337 0 0 0-.114-.217.526.526 0 0 0-.31-.093c-.3.005-.763.166-2.984 1.09z"/>
                </svg><span class="">{{ __('pages.about.chat') }}</span>
            </a>
            <a href="https://t.me/rodniktoday" target="_blank" class="mt-2 block font-normal text-blue-600 hover:text-blue-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="inline mr-1" viewBox="0 0 16 16">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8.287 5.906c-.778.324-2.334.994-4.666 2.01-.378.15-.577.298-.595.442-.03.243.275.339.69.47l.175.055c.408.133.958.288 1.243.294.26.006.549-.1.868-.32 2.179-1.471 3.304-2.214 3.374-2.23.05-.012.12-.026.166.016.047.041.042.12.037.141-.03.129-1.227 1.241-1.846 1.817-.193.18-.33.307-.358.336a8.154 8.154 0 0 1-.188.186c-.38.366-.664.64.015 1.088.327.216.589.393.85.571.284.194.568.387.936.629.093.06.183.125.27.187.331.236.63.448.997.414.214-.02.435-.22.547-.82.265-1.417.786-4.486.906-5.751a1.426 1.426 0 0 0-.013-.315.337.337 0 0 0-.114-.217.526.526 0 0 0-.31-.093c-.3.005-.763.166-2.984 1.09z"/>
                </svg><span class="">{{ __('pages.about.notifications_channel') }}</span>
            </a>
        </div>
    </div>
@endsection
