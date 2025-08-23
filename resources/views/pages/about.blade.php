@extends('folio.index')

@section('content')
    <div class="prose">
        <div class="font-black text-2xl">
            Rodnik.today is a social layer on top of OpenStreetMap for
            exploring and sharing information about public water sources
        </div>
        <div class="mt-3 max-w-prose">
            Rodnik.today uses OpenStreetMap as the source of data.
            Use it to explore public water sources or plan
            water supply during your long trips. Share
            local knowledge and up-to-date information
            with other community enthusiasts.
        </div>
        <div class="mt-3">
            Verified information gets reported back to OpenStreetMap
            and improves quality of maps all over the world.
        </div>
        <div class="mt-9 font-black text-xl">
            Copyright and license
        </div>
        <div class="mt-3">
            All data reported by our users is in the public domain, although the bulk of the information
            comes from OpenStreetMap, which is licensed under the  
            <a href="https://www.openstreetmap.org/copyright" class="text-blue-600" target="_blank">ODbL</a>.
            We are enternally grateful to the OpenStreetMap community for their work.
        </div>

        <div class="mt-9 font-black text-xl">
            Authors
        </div>
        <div class="mt-3">
            Rodnik.today is created by a community of outdoor enthusiasts.
            The project is non-profit and non-commercial. We love nature and
            we want to make the information accessible to everyone. <b>You too?
            Please, join our community!</b>
        </div>

        <div class="mt-9 font-black text-xl">
            More information
        </div>
        <ul class="mt-3">
            <li class="list-disc">
                <a href="https://docs.google.com/document/d/173TpVT7EQCEVaLyL3uB9dsjSwYiSzZP-jeKSuRPVwfM/edit" class="text-blue-600">Learn more about Rodnik.today</a> <i>(Google Document)</i>.
            </li>
            <li>
                <a href="https://docs.google.com/spreadsheets/d/1sDnIOWgyEtAAMGeFpSk0KXX2Qfu8A3PpZNFN_MxcWN0/edit?gid=0#gid=0" class="text-blue-600" target="_blank">
                    Working document on the taxonomy of water sources
                </a> (Google Spreadsheet)
            </li>
        </ul>

        <div class="mt-9 font-black text-xl">
            Keep in touch
        </div>

        <div class="mt-3">
            <a href="https://t.me/rodnik_today" target="_blank" class="block font-normal text-blue-600 hover:text-blue-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="inline mr-1" viewBox="0 0 16 16">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8.287 5.906c-.778.324-2.334.994-4.666 2.01-.378.15-.577.298-.595.442-.03.243.275.339.69.47l.175.055c.408.133.958.288 1.243.294.26.006.549-.1.868-.32 2.179-1.471 3.304-2.214 3.374-2.23.05-.012.12-.026.166.016.047.041.042.12.037.141-.03.129-1.227 1.241-1.846 1.817-.193.18-.33.307-.358.336a8.154 8.154 0 0 1-.188.186c-.38.366-.664.64.015 1.088.327.216.589.393.85.571.284.194.568.387.936.629.093.06.183.125.27.187.331.236.63.448.997.414.214-.02.435-.22.547-.82.265-1.417.786-4.486.906-5.751a1.426 1.426 0 0 0-.013-.315.337.337 0 0 0-.114-.217.526.526 0 0 0-.31-.093c-.3.005-.763.166-2.984 1.09z"/>
                </svg><span class="">Chat</span>
            </a>
            <a href="https://t.me/rodniktoday" target="_blank" class="mt-2 block font-normal text-blue-600 hover:text-blue-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="inline mr-1" viewBox="0 0 16 16">
                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8.287 5.906c-.778.324-2.334.994-4.666 2.01-.378.15-.577.298-.595.442-.03.243.275.339.69.47l.175.055c.408.133.958.288 1.243.294.26.006.549-.1.868-.32 2.179-1.471 3.304-2.214 3.374-2.23.05-.012.12-.026.166.016.047.041.042.12.037.141-.03.129-1.227 1.241-1.846 1.817-.193.18-.33.307-.358.336a8.154 8.154 0 0 1-.188.186c-.38.366-.664.64.015 1.088.327.216.589.393.85.571.284.194.568.387.936.629.093.06.183.125.27.187.331.236.63.448.997.414.214-.02.435-.22.547-.82.265-1.417.786-4.486.906-5.751a1.426 1.426 0 0 0-.013-.315.337.337 0 0 0-.114-.217.526.526 0 0 0-.31-.093c-.3.005-.763.166-2.984 1.09z"/>
                </svg><span class="">Notifications Channel</span>
            </a>
        </div>
    </div>
@endsection
