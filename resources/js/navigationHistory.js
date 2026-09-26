function sameOriginUrl(window, href) {
    const current = new URL(window.location.href);
    const url = new URL(href, current);
    if (url.origin !== current.origin || url.protocol !== current.protocol) {
        throw new TypeError('Navigation must stay on the current origin.');
    }
    return url;
}

export function writeDuoHistory(window, href, { replace = false, localTrack = null } = {}) {
    const url = sameOriginUrl(window, href);
    const state = { ...window.history.state };
    const alpine = { ...state.alpine };
    delete alpine.url;
    delete alpine.snapshotIdx;
    if (Object.keys(alpine).length) state.alpine = alpine;
    else delete state.alpine;
    state.rodnik = { ...state.rodnik, duo: true, url: url.href };
    delete state.rodnik.localTrackId;
    delete state.rodnik.localTrackOwnerId;
    if (localTrack) {
        state.rodnik.localTrackId = localTrack.id;
        state.rodnik.localTrackOwnerId = localTrack.ownerId;
    }
    window.history[replace ? 'replaceState' : 'pushState'](state, '', url.href);
    return url;
}

export function navigateHistoryWithLivewire(window) {
    const url = sameOriginUrl(window, window.location.href);
    const state = {
        ...window.history.state,
        alpine: {
            ...window.history.state?.alpine,
            snapshotIdx: `rodnik-history:${window.crypto.randomUUID()}`,
            url: url.href,
        },
    };
    // An uncached popstate uses Livewire's page fetch and replace-history path.
    window.history.replaceState(state, '', url.href);
    window.dispatchEvent(new window.PopStateEvent('popstate', { state }));
    return url;
}
