const storagePrefix = 'rodnik:map-draft:';
const draftsByWindow = new WeakMap();

function draftKey(ownerId) {
    const id = String(ownerId ?? '');
    return /^[1-9]\d*$/.test(id) ? `${storagePrefix}${id}` : null;
}

function metadata(value) {
    if (!value || typeof value !== 'object' || Array.isArray(value) || typeof value.title !== 'string') return null;
    if (value.slug != null && typeof value.slug !== 'string') return null;
    const draft = {
        title: value.title.trim(),
        slug: (value.slug ?? '').trim(),
    };
    if (!draft.title || draft.title.length > 160 || draft.slug.length > 80) return null;
    return draft;
}

function memory(window) {
    if (!draftsByWindow.has(window)) draftsByWindow.set(window, new Map());
    return draftsByWindow.get(window);
}

function notify(window, ownerId, draft) {
    window.dispatchEvent?.(new CustomEvent('map-draft-changed', {
        detail: { ownerId: String(ownerId), draft: draft ? { ...draft } : null },
    }));
}

export function readMapDraft(ownerId) {
    const key = draftKey(ownerId);
    const window = globalThis.window;
    if (!key || !window) return null;
    const drafts = memory(window);
    if (drafts.has(key)) return drafts.get(key) ? { ...drafts.get(key) } : null;

    try {
        const draft = metadata(JSON.parse(window.sessionStorage.getItem(key)));
        drafts.set(key, draft);
        return draft ? { ...draft } : null;
    } catch {
        return null;
    }
}

export function writeMapDraft(value, ownerId) {
    const key = draftKey(ownerId);
    const draft = metadata(value);
    const window = globalThis.window;
    if (!key || !draft || !window) return false;

    memory(window).set(key, draft);
    try { window.sessionStorage.setItem(key, JSON.stringify(draft)); } catch { /* Keep the draft through Livewire navigation when storage is unavailable. */ }
    notify(window, ownerId, draft);
    return true;
}

export function clearMapDraft(ownerId) {
    const key = draftKey(ownerId);
    const window = globalThis.window;
    if (!key || !window) return;

    memory(window).set(key, null);
    try { window.sessionStorage.removeItem(key); } catch { /* A memory tombstone prevents an older stored draft from reappearing. */ }
    notify(window, ownerId, null);
}
