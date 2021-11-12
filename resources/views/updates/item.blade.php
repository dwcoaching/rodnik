<div class="bg-white shadow-xl rounded-lg p-4 mt-4">
    <div>
        <span class="font-bold">{{ Date::parse($update->created_at)->format('j F Y') }}</span>
    </div>
    <div class="max-w-prose mt-2">
        {{ $update->comment }}
    </div>
</div>
