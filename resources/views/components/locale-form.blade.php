@props(['locale'])

{{--
    Posts a locale switch and returns the visitor to the page they were on.

    The server renders the localized path of the current request, but Livewire's
    `#[Url]` attribute rewrites the query string client-side long after the layout
    was rendered (map state on the duo page, for instance). The path is kept as the
    server resolved it — it encodes the locale prefix decision — while the query
    string and fragment are re-read from the browser on submit.
--}}
<form
    method="POST"
    action="{{ route('locale.update', ['locale' => $locale]) }}"
    x-data
    @submit="$refs.localeRedirect.value = $refs.localeRedirect.value.split(/[?#]/)[0]
        + window.location.search
        + window.location.hash"
    {{ $attributes }}
>
    @csrf
    <input type="hidden" name="redirect" x-ref="localeRedirect" value="{{ localized_path($locale) }}">
    {{ $slot }}
</form>
