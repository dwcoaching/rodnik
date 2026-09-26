import { localizedNavigationUrl, resourceNavigationUrl, NavigationRequests } from './navigationState.js';
import { initialMapConfiguration, mapStateUrl, parseMapUrlState } from './mapUrlState.js';
import { writeDuoHistory, navigateHistoryWithLivewire } from './navigationHistory.js';
import { captureLocalTrackHistory, readLocalTrackHistory } from './localTrackHistory.js';

export function refreshTranslations({ sharedMap = null, ownerId = null } = {}) {
    const element = document.querySelector('body #rodnik-translations') ?? document.getElementById('rodnik-translations');
    if (!element) return;
    const { locale, publicBaseUrl, translations, mapTranslations, ownerId: nextOwnerId = null } = JSON.parse(element.textContent);
    window.rodnikLocale = locale;
    window.rodnikOwnerId = nextOwnerId;
    window.rodnikPublicBaseUrl = publicBaseUrl;
    window.rodnikTranslations = translations;
    window.rodnikMapTranslations = mapTranslations;
    const shared = document.getElementById('rodnik-shared-map');
    const record = shared ? JSON.parse(shared.textContent) : null;
    window.rodnikSharedMap = record ?? (String(ownerId ?? '') === String(nextOwnerId ?? '') ? sharedMap : null);
    return record;
}

export function installNavigation(Livewire) {
    refreshTranslations();
    const requests = new NavigationRequests();
    let activeUrl = window.location.href;
    let generation = 0;
    let pendingVisit = null;
    let componentRequest = false;
    let pageContext = null;
    let pageOptions = {};
    let passingPageNavigation = false;
    let passingHistoryEvent = false;
    let urlTimer = null;
    let localeGeneration = 0;
    let localeQueue = Promise.resolve();
    let lastMapUrl = null;

    const duo = () => Livewire.getByName('duo')[0];
    const pageData = () => {
        const element = document.querySelector('[data-rodnik-page]');
        return element ? JSON.parse(element.dataset.rodnikPage) : null;
    };
    const localeOf = url => /^\/ru(?:\/|$)/.test(url.pathname) ? 'ru' : 'en';
    const isResource = url => url.origin === window.location.origin
        && /^\/(?:\d+\/?|users\/\d+\/?)?$/.test(url.pathname.replace(/^\/ru(?=\/|$)/, '') || '/');
    const isSavedMap = url => url.origin === window.location.origin
        && /^\/(?:ru\/)?maps\/[^/]+\/?$/.test(url.pathname);
    const sameResource = (left, right) => left.pathname === right.pathname && left.search === right.search;

    function liveUrl(href) {
        const map = window.rodnikMap;
        return map?.captureSharedState ? mapStateUrl(href, map.captureSharedState(), map.sharedTrack?.token) : new URL(href);
    }

    const localTrack = () => captureLocalTrackHistory(window, window.rodnikMap, window.rodnikOwnerId);

    function syncMapUrl() {
        if (navigation.restoring || !pageData() || !window.rodnikMap?.captureSharedState) return;
        const current = new URL(window.location.href);
        const base = isSavedMap(current)
            ? new URL(window.rodnikSharedMap?.resource_url ?? resourceNavigationUrl(current.href, pageData().page).href)
            : current;
        const url = liveUrl(base.href);
        const privateTrack = localTrack();
        if (url.href !== current.href || !window.history.state?.rodnik?.duo || window.history.state.rodnik.url !== url.href
            || (window.history.state.rodnik.localTrackId ?? null) !== (privateTrack?.id ?? null)
            || (privateTrack && window.history.state.rodnik.localTrackOwnerId !== privateTrack.ownerId)) {
            writeDuoHistory(window, url, { replace: true, localTrack: privateTrack });
        }
        activeUrl = url.href;
        lastMapUrl = { href: url.href, ownerId: window.rodnikOwnerId };
        prepareLinks();
    }

    function scheduleUrlSync() {
        clearTimeout(urlTimer);
        urlTimer = setTimeout(() => { urlTimer = null; syncMapUrl(); }, 150);
    }

    function applyMetadata(metadata) {
        if (!metadata) return;
        document.title = metadata.title;
        const set = (selector, attribute, value) => document.querySelector(selector)?.setAttribute(attribute, value);
        set('meta[name="description"]', 'content', metadata.description);
        set('link[rel="canonical"]', 'href', metadata.canonical);
        set('meta[property="og:title"]', 'content', metadata.title);
        set('meta[property="og:description"]', 'content', metadata.description);
        set('meta[property="og:url"]', 'content', metadata.canonical);
        document.querySelector('meta[name="robots"]')?.remove();
        if (metadata.robots) {
            const robots = document.createElement('meta');
            robots.name = 'robots';
            robots.content = metadata.robots;
            document.head.append(robots);
        }
        document.querySelectorAll('link[rel="alternate"][hreflang]').forEach(element => element.remove());
        for (const [locale, href] of Object.entries(metadata.alternates ?? {})) {
            const alternate = document.createElement('link');
            alternate.rel = 'alternate';
            alternate.hreflang = locale;
            alternate.href = href;
            document.head.append(alternate);
        }
    }

    async function applyFragment(url, page, revision, { history = false } = {}) {
        const parsed = parseMapUrlState(url.href);
        const map = window.rodnikMap;
        if (!map) return false;
        await map.ready;
        if (revision !== generation) return false;
        if (!parsed) {
            const track = initialMapConfiguration(url.href, page).track;
            if (!track) return false;
            map.loadSharedTrack(track, { fit: true });
            return true;
        }
        const privateTrack = history ? readLocalTrackHistory(window, window.rodnikOwnerId, url.href) : null;
        const track = parsed.trackToken !== null && map.sharedTrack?.token === parsed.trackToken ? undefined : parsed.trackToken;
        await map.restoreSharedState({ ...parsed.state, page }, { track });
        if (revision === generation && privateTrack) map.restoreLocalTrack(privateTrack);
        return revision === generation;
    }

    function completeVisit() {
        navigation.restoring = false;
        syncMapUrl();
        prepareLinks();
        window.dispatchEvent(new CustomEvent('map-viewport-changed'));
        window.dispatchEvent(new CustomEvent('rodnik:navigated'));
    }

    async function processVisits() {
        if (componentRequest) return;
        componentRequest = true;
        try {
            while (pendingVisit) {
                const visit = pendingVisit;
                pendingVisit = null;
                const { url, options, revision } = visit;
                const wire = duo();
                if (!wire || !isResource(url) || localeOf(url) !== window.rodnikLocale) {
                    startPageVisit(visit);
                    break;
                }
                try {
                    window.destroyPhotoSwipes?.();
                    const result = await wire.navigateTo(url.pathname + url.search);
                    if (revision !== generation) continue;
                    const map = window.rodnikMap;
                    await map?.ready;
                    if (revision !== generation) continue;
                    const restored = options.restoreUrl ? await applyFragment(url, result.page, revision, options) : false;
                    if (revision !== generation) continue;
                    const privateTrack = options.history ? readLocalTrackHistory(window, window.rodnikOwnerId, url.href) : null;
                    if (privateTrack && map?.sharedTrack?.status === 'idle') map.restoreLocalTrack(privateTrack);
                    applyMetadata(result.metadata);
                    if (options.refresh) map?.refreshSpringData?.();
                    map?.duoVisit({
                        ...result.page,
                        coordinates: result.coordinates?.length ? result.coordinates : null,
                        preserveMapView: Boolean(options.preserveMap || restored),
                        preserveMapViewIfVisible: Boolean(options.preserveMapIfVisible),
                    });
                    await new Promise(resolve => window.Alpine.nextTick(resolve));
                    if (revision !== generation) continue;
                    const destination = liveUrl(new URL(result.url, url).href);
                    writeDuoHistory(window, destination, {
                        replace: Boolean(options.history || sameResource(new URL(activeUrl), destination)),
                        localTrack: localTrack(),
                    });
                    activeUrl = destination.href;
                    completeVisit();
                    if (!options.preserveScroll && !options.history) window.scrollTo({ top: 0, behavior: 'instant' });
                    window.ym?.(90143259, 'hit', destination.href);
                } catch (error) {
                    if (revision !== generation) continue;
                    navigation.restoring = false;
                    if (options.history) writeDuoHistory(window, liveUrl(activeUrl), { replace: true, localTrack: localTrack() });
                    window.dispatchEvent(new CustomEvent('map-viewport-changed'));
                    console.error('Could not update the map page', error);
                }
            }
        } finally {
            componentRequest = false;
        }
    }

    function startPageVisit({ url, options, revision }) {
        pageOptions = { ...options, revision };
        passingPageNavigation = true;
        try {
            if (options.history) {
                passingHistoryEvent = true;
                try { navigateHistoryWithLivewire(window); } finally { passingHistoryEvent = false; }
            } else {
                Livewire.navigate(url.href, { preserveScroll: Boolean(options.preserveScroll) });
            }
        } finally {
            passingPageNavigation = false;
        }
    }

    function refreshPageTranslations() {
        const visit = pageContext;
        const retain = visit && visit.map && visit.map === window.rodnikMap && isResource(new URL(window.location.href));
        return refreshTranslations(retain ? { sharedMap: visit.sharedMap, ownerId: visit.ownerId } : {});
    }

    async function finishPageVisit() {
        const visit = pageContext;
        const revision = generation;
        const incomingSharedMap = refreshPageTranslations();
        const data = pageData();
        const map = window.rodnikMap;
        if (data && map) {
            await map.ready;
            if (revision !== generation) return;
            let restored = !visit && Boolean(map.initialSharedState);
            if (visit && visit.map === map && !visit.options.preserveMap) {
                if (incomingSharedMap) {
                    await map.restoreSharedState({ ...incomingSharedMap.state, page: data.page }, { track: incomingSharedMap.track });
                    restored = true;
                } else if (visit.options.restoreUrl && !visit.options.history) {
                    restored = await applyFragment(new URL(window.location.href), data.page, revision);
                }
            } else if (visit && visit.map !== map) {
                restored = Boolean(map.initialSharedState);
            }
            if (revision !== generation) return;
            map.refreshLocale();
            if (visit?.options.refresh && visit.map === map) map.refreshSpringData?.();
            map.duoVisit({
                ...data.page,
                coordinates: data.coordinates?.length ? data.coordinates : null,
                preserveMapView: Boolean(restored || visit?.options.preserveMap),
                preserveMapViewIfVisible: Boolean(visit?.options.preserveMapIfVisible),
            });
            await new Promise(resolve => window.Alpine.nextTick(resolve));
        }
        if (revision !== generation) return;
        activeUrl = window.location.href;
        pageContext = null;
        completeVisit();
        if (visit) window.ym?.(90143259, 'hit', window.location.href);
    }

    const navigation = {
        restoring: true,
        // Map controls call this before sharing; the URL always represents the live map.
        capture: syncMapUrl,
        mapUrl(fallback, ownerId) {
            syncMapUrl();
            if (!lastMapUrl || String(ownerId ?? '') !== String(window.rodnikOwnerId ?? '')
                || String(ownerId ?? '') !== String(lastMapUrl.ownerId ?? '')) return fallback;
            return localizedNavigationUrl(lastMapUrl.href, localeOf(new URL(fallback, window.location.href))).href;
        },
        savedMap(record) {
            window.rodnikSharedMap = record;
            const element = document.getElementById('rodnik-shared-map');
            if (element) element.textContent = JSON.stringify(record);
            syncMapUrl();
        },
        localizedUrl: locale => localizedNavigationUrl(window.location.href, locale).href,
        visit(href, options = {}) {
            const url = new URL(href, window.location.href);
            if (url.origin !== window.location.origin) {
                window.location.assign(url.href);
                return;
            }
            if (url.href === activeUrl && !options.history && !options.refresh && !componentRequest && !pageContext) return;
            if (!options.history && !navigation.restoring) syncMapUrl();
            const revision = ++generation;
            requests.cancel();
            clearTimeout(urlTimer);
            pageContext = null;
            navigation.restoring = true;
            pendingVisit = { url, options, revision };
            return processVisits();
        },
        changeLocale(locale, action) {
            const revision = ++localeGeneration;
            localeQueue = localeQueue.catch(() => {}).then(async () => {
                const response = await fetch(action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ redirect: new URL(navigation.localizedUrl(locale)).pathname + window.location.search }),
                });
                if (!response.ok) throw new Error('Language preference could not be saved.');
                if (revision === localeGeneration) navigation.visit(navigation.localizedUrl(locale), { preserveMap: true, preserveScroll: true });
            });
            localeQueue.catch(error => {
                if (revision === localeGeneration) console.error('Could not change language', error);
            });
        },
    };
    window.rodnikNavigation = navigation;

    Livewire.hook('navigate.request', ({ options }) => requests.attach(options));
    window.addEventListener('unhandledrejection', event => {
        if (requests.owns(event.reason)) event.preventDefault();
    });
    window.addEventListener('popstate', event => {
        if (passingHistoryEvent) return;
        event.stopImmediatePropagation();
        const url = new URL(window.location.href);
        const previous = new URL(activeUrl);
        if (duo() && sameResource(url, previous) && url.hash !== previous.hash && parseMapUrlState(url.href)) {
            if (componentRequest) {
                navigation.visit(url.href, { history: true, restoreUrl: true, preserveMap: true });
                return;
            }
            const revision = ++generation;
            requests.cancel();
            pendingVisit = null;
            navigation.restoring = true;
            applyFragment(url, pageData().page, revision, { history: true }).then(() => {
                if (revision !== generation) return;
                activeUrl = url.href;
                completeVisit();
            }).catch(error => {
                if (revision !== generation) return;
                navigation.restoring = false;
                console.error('Could not restore map URL', error);
            });
            return;
        }
        navigation.visit(url.href, { history: true });
    }, { capture: true });
    window.addEventListener('hashchange', () => {
        const url = new URL(window.location.href);
        if (!duo() || url.href === activeUrl || !sameResource(url, new URL(activeUrl))) return;
        if (componentRequest && parseMapUrlState(url.href)) {
            navigation.visit(url.href, { history: true, restoreUrl: true, preserveMap: true });
            return;
        }
        if (navigation.restoring) return;
        const revision = ++generation;
        navigation.restoring = true;
        applyFragment(url, pageData().page, revision).then(() => {
            if (revision !== generation) return;
            activeUrl = url.href;
            completeVisit();
        }).catch(error => {
            if (revision !== generation) return;
            navigation.restoring = false;
            console.error('Could not restore map URL', error);
        });
    });
    document.addEventListener('livewire:navigate', event => {
        if (event.defaultPrevented) return;
        if (!passingPageNavigation) {
            event.preventDefault();
            navigation.visit(event.detail.history ? window.location.href : event.detail.url, { history: event.detail.history, refresh: true });
            return;
        }
        requests.start();
        navigation.restoring = true;
        pageContext = {
            options: pageOptions, map: window.rodnikMap, revision: generation,
            sharedMap: window.rodnikSharedMap, ownerId: window.rodnikOwnerId,
        };
        pageOptions = {};
    });
    document.addEventListener('livewire:navigating', event => {
        window.destroyPhotoSwipes?.();
        event.detail.onSwap(refreshPageTranslations);
    });
    document.addEventListener('livewire:navigated', () => {
        const revision = generation;
        finishPageVisit().catch(error => {
            if (revision !== generation) return;
            navigation.restoring = false;
            console.error('Could not initialize the map page', error);
        });
    });

    function prepareLinks() {
        const current = new URL(window.location.href);
        document.querySelectorAll('a[href]').forEach(anchor => {
            if (anchor.hasAttribute('data-rodnik-exact-url')) return;
            anchor.dataset.rodnikBaseHref ??= anchor.href;
            const url = new URL(anchor.dataset.rodnikBaseHref, current);
            if (url.origin !== current.origin) return;
            if (isResource(url) || isSavedMap(url) || /^\/(?:ru\/)?(?:user\/maps(?:\/|$)|docs(?:\/|$))/.test(url.pathname)
                || anchor.closest('[data-rodnik-pagination]')) anchor.dataset.rodnikNavigate = '';
            if (anchor.dataset.rodnikLocale && anchor.hasAttribute('data-rodnik-navigate')) {
                anchor.href = navigation.localizedUrl(anchor.dataset.rodnikLocale);
                return;
            }
            if (!anchor.hasAttribute('data-rodnik-navigate') || !isResource(url)) return;
            const contextual = resourceNavigationUrl(current.href, {});
            for (const [key, value] of contextual.searchParams) url.searchParams.set(key, value);
            if (!url.hash) url.hash = current.hash;
            anchor.href = url.href;
        });
    }
    Livewire.hook('morphed', prepareLinks);
    document.addEventListener('pointerdown', prepareLinks, { capture: true });
    document.addEventListener('click', event => {
        const anchor = event.target.closest?.('a[data-rodnik-navigate]');
        if (!anchor || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.altKey || event.shiftKey
            || anchor.hasAttribute('download') || (anchor.target && anchor.target !== '_self')) return;
        const url = new URL(anchor.href);
        if (url.origin !== window.location.origin) return;
        event.preventDefault();
        if (anchor.dataset.rodnikLocale) {
            navigation.changeLocale(anchor.dataset.rodnikLocale, anchor.dataset.rodnikLocaleAction);
        } else {
            navigation.visit(url.href, {
                preserveMapIfVisible: anchor.hasAttribute('data-rodnik-preserve-map'),
                restoreUrl: Boolean(new URL(anchor.dataset.rodnikBaseHref ?? anchor.href).hash || url.searchParams.has('t')),
            });
        }
    });
    document.addEventListener('submit', event => {
        const form = event.target.closest?.('form[data-rodnik-locale]');
        if (!form || !pageData()) return;
        event.preventDefault();
        navigation.changeLocale(form.dataset.rodnikLocale, form.action);
    });
    window.addEventListener('duo-visit', event => {
        navigation.visit(resourceNavigationUrl(window.location.href, event.detail).href, {
            preserveMap: Boolean(event.detail.preserveMapView),
            restoreUrl: false,
        });
    });
    for (const name of ['map-state-changed', 'map-viewport-changed', 'map-filters-changed', 'map-track-changed']) {
        window.addEventListener(name, scheduleUrlSync, { passive: true });
    }
    window.addEventListener('map-track-persistence-changed', syncMapUrl, { passive: true });
    window.addEventListener('pagehide', () => clearTimeout(urlTimer));
    return navigation;
}
