import assert from 'node:assert/strict';
import { registerHooks } from 'node:module';
import test from 'node:test';
import Coordinates from 'coordinate-parser';
import View from 'ol/View.js';
import Observable from 'ol/Observable.js';
import KeyboardPan from 'ol/interaction/KeyboardPan.js';
import VectorSource from 'ol/source/Vector.js';
import VectorLayer from 'ol/layer/Vector.js';
import { fromLonLat, toLonLat, transformExtent } from 'ol/proj.js';
import createMapView from '../../resources/js/mapView.js';
import mapSearch, {
    LruCache,
    addRecent,
    announceDelay,
    cacheKey,
    choosePlural,
    coordinateText,
    createDebouncer,
    focusRequest,
    foldText,
    highlightSegments,
    isSearchableQuery,
    linkedViewResult,
    matchSearchHotkey,
    normalizeQuery,
    normalizeSearchResponse,
    parseCoordinateQuery,
    readRecents,
    recentStorageKey,
    resultLimit,
    resultSections,
    searchTranslations,
    searchUrl,
    viewBias,
    writeRecents,
} from '../../resources/js/mapSearch.js';

const browserImports = registerHooks({
    resolve(specifier, context, nextResolve) {
        if (specifier === 'exifr') {
            return nextResolve('exifr/dist/full.esm.mjs', context);
        }
        if (specifier.startsWith('@/')) {
            const path = specifier.slice(2).replace(/(?:\.js)?$/, '.js');
            return nextResolve(new URL(`../../resources/js/${path}`, import.meta.url).href, context);
        }
        if (specifier.startsWith('ol/') && !specifier.endsWith('.js')) {
            return nextResolve(`${specifier}.js`, context);
        }
        return nextResolve(specifier, context);
    },
});
const { default: OpenLayersMap } = await import('../../resources/js/openLayers.js');
browserImports.deregister();

const key = (properties) => ({ key: '', code: '', ctrlKey: false, metaKey: false, altKey: false, shiftKey: false, ...properties });
const text = segments => segments.map(segment => (segment.match ? `[${segment.text}]` : segment.text)).join('');
const flush = () => new Promise(resolve => setImmediate(resolve));

test('slash opens search on US, Russian and German layouts but not with Shift+Slash', () => {
    assert.equal(matchSearchHotkey(key({ key: '/', code: 'Slash' })), 'slash');
    assert.equal(matchSearchHotkey(key({ key: '.', code: 'Slash' })), 'slash', 'Russian layout types "." on the slash key');
    assert.equal(matchSearchHotkey(key({ key: '/', code: 'Digit7', shiftKey: true })), 'slash', 'German layout types "/" with Shift+7');
    assert.equal(matchSearchHotkey(key({ key: '/', code: 'NumpadDivide' })), 'slash');
    assert.equal(matchSearchHotkey(key({ key: '?', code: 'Slash', shiftKey: true })), null);
    assert.equal(matchSearchHotkey(key({ key: ',', code: 'Slash', shiftKey: true })), null, 'Russian Shift+Slash is a comma');
    assert.equal(matchSearchHotkey(key({ key: '/', code: 'Slash', ctrlKey: true })), null);
    assert.equal(matchSearchHotkey(key({ key: '/', code: 'Slash', altKey: true })), null);
    assert.equal(matchSearchHotkey(key({ key: 'k', code: 'KeyK' })), null);
});

test('the slash key on other layouts keeps its own character', () => {
    assert.equal(matchSearchHotkey(key({ key: '-', code: 'Slash' })), null, 'German, Nordic and Spanish layouts type "-" there');
    assert.equal(matchSearchHotkey(key({ key: '!', code: 'Slash' })), null, 'French AZERTY');
    assert.equal(matchSearchHotkey(key({ key: 'z', code: 'Slash' })), null, 'Dvorak');
    assert.equal(matchSearchHotkey(key({ key: ';', code: 'Slash' })), null, 'Brazilian ABNT2');
    assert.equal(matchSearchHotkey(key({ key: 'Dead', code: 'Slash' })), null);
});

test('slash is ignored while typing, composing or after another handler', () => {
    for (const target of [
        { tagName: 'INPUT', type: 'text' },
        { tagName: 'input', type: 'search' },
        { tagName: 'TEXTAREA' },
        { tagName: 'SELECT' },
        { tagName: 'DIV', isContentEditable: true },
    ]) {
        assert.equal(matchSearchHotkey(key({ key: '/', code: 'Slash', target })), null, JSON.stringify(target));
    }
    assert.equal(matchSearchHotkey(key({ key: '/', code: 'Slash', target: { tagName: 'INPUT', type: 'checkbox' } })), 'slash');
    assert.equal(matchSearchHotkey(key({ key: '/', code: 'Slash', target: { tagName: 'BUTTON' } })), 'slash');
    assert.equal(matchSearchHotkey(key({ key: '/', code: 'Slash', isComposing: true })), null);
    assert.equal(matchSearchHotkey(key({ key: '/', code: 'Slash', defaultPrevented: true })), null);
});

test('Cmd+K on Mac and Ctrl+K elsewhere toggle search on any layout, even inside inputs', () => {
    const mac = { isMac: true };
    const windows = { isMac: false };
    const input = { tagName: 'INPUT', type: 'text' };

    assert.equal(matchSearchHotkey(key({ key: 'k', code: 'KeyK', metaKey: true }), mac), 'mod-k');
    assert.equal(matchSearchHotkey(key({ key: 'л', code: 'KeyK', metaKey: true }), mac), 'mod-k');
    assert.equal(matchSearchHotkey(key({ key: 'k', code: 'KeyK', metaKey: true, target: input }), mac), 'mod-k');
    assert.equal(matchSearchHotkey(key({ key: 'k', code: 'KeyK', ctrlKey: true }), mac), null, 'Ctrl+K is a text editing key on Mac');
    assert.equal(matchSearchHotkey(key({ key: 'k', code: 'KeyK', ctrlKey: true }), windows), 'mod-k');
    assert.equal(matchSearchHotkey(key({ key: 'л', code: 'KeyK', ctrlKey: true }), windows), 'mod-k');
    assert.equal(matchSearchHotkey(key({ key: 'K', code: 'KeyK', metaKey: true, shiftKey: true }), mac), null);
    assert.equal(matchSearchHotkey(key({ key: 'k', code: 'KeyK', metaKey: true, altKey: true }), mac), null);
    assert.equal(matchSearchHotkey(key({ key: 'k', code: 'KeyK', ctrlKey: true, altKey: true }), windows), null);
});

test('queries are trimmed and folded like the database collation', () => {
    assert.equal(normalizeQuery('  Нижний \n  Новгород  '), 'Нижний Новгород');
    assert.equal(foldText('Ёлкино'), 'елкино');
    assert.equal(foldText('Zürich São Paulo Łódź'), 'zurich sao paulo lodz');
    assert.equal(foldText('Йошкар-Ола'), 'йошкар-ола', 'й is a distinct letter');
    assert.equal(foldText('Straße'), 'strasse');
    assert.equal(cacheKey('ru', ' ЁЛКИ  Палки '), cacheKey('ru', 'ёлки палки'));
    assert.equal(cacheKey('en', 'Zu\u0308rich'), cacheKey('en', 'zürich'), 'composed and decomposed accents are one query');
    assert.notEqual(cacheKey('ru', 'елки'), cacheKey('en', 'елки'));
    for (const [folded, exact] of [['Wroclaw', 'Wrocław'], ['Lodz', 'Łódź'], ['Tromso', 'Tromsø'], ['Киів', 'Київ'], ['усход', 'ўсход']]) {
        assert.notEqual(cacheKey('en', folded), cacheKey('en', exact), `the database tells ${folded} and ${exact} apart`);
    }
    assert.equal(isSearchableQuery('м'), false);
    assert.equal(isSearchableQuery('мо'), true);
    assert.equal(isSearchableQuery('7'), true, 'a water source number may be one digit');
    assert.equal(isSearchableQuery('#7'), true);
    assert.equal(isSearchableQuery('№7'), true, 'Shift+3 on the Russian layout');
    assert.equal(isSearchableQuery('#'), false);
    assert.equal(isSearchableQuery('№'), false);
    assert.equal(searchUrl('https://rodnik.test/ru/', 'Ростов-на-Дону & co'),
        'https://rodnik.test/ru/search/places?q=%D0%A0%D0%BE%D1%81%D1%82%D0%BE%D0%B2-%D0%BD%D0%B0-%D0%94%D0%BE%D0%BD%D1%83%20%26%20co');
});

test('the visible map area is sent only when it is zoomed in enough to disambiguate', () => {
    assert.equal(viewBias({ west: 35.123456, south: 54.5, east: 40.5, north: 57.009 }), '35.12,54.5,40.5,57.01');
    assert.equal(viewBias({ west: 37.2913, south: 56.8121, east: 37.2961, north: 56.8152 }), '37.29,56.81,37.3,56.82',
        'a zoomed-in view rounds outward instead of collapsing to an empty box');
    assert.equal(viewBias({ west: -0.004, south: -0.004, east: 0.004, north: 0.004 }), '-0.01,-0.01,0.01,0.01');
    assert.equal(viewBias({ west: 179.995, south: 89.995, east: 180, north: 90 }), '179.99,89.99,180,90', 'edges stay within the world');
    assert.equal(viewBias({ west: 175.5, south: 60, east: -170.25, north: 70 }), '175.5,60,-170.25,70', 'a view across the antimeridian keeps west > east');
    assert.equal(viewBias({ west: 0, south: 40, east: 25, north: 50 }), '', 'wider than 20 degrees');
    assert.equal(viewBias({ west: 0, south: 30, east: 10, north: 55 }), '', 'taller than 20 degrees');
    assert.equal(viewBias({ west: 0, south: 50, east: 10, north: 50 }), '');
    assert.equal(viewBias({ west: Number.NaN, south: 1, east: 2, north: 3 }), '');
    assert.equal(viewBias(null), '');

    assert.equal(searchUrl('https://rodnik.test', 'мос', '35.12,54.5,40.5,57.01'),
        'https://rodnik.test/search/places?q=%D0%BC%D0%BE%D1%81&bbox=35.12,54.5,40.5,57.01');
    assert.notEqual(cacheKey('ru', 'мос', '35.12,54.5,40.5,57.01'), cacheKey('ru', 'мос'));
});

test('highlighting marks the matched part of names in any script', () => {
    assert.equal(text(highlightSegments('Москва', 'мос')), '[Мос]ква');
    assert.equal(text(highlightSegments('Ёлкино', 'елк')), '[Ёлк]ино');
    assert.equal(text(highlightSegments('Елкино', 'ёлк')), '[Елк]ино');
    assert.equal(text(highlightSegments('Zürich', 'zur')), '[Zür]ich');
    assert.equal(text(highlightSegments('Straße', 'strasse')), '[Straße]');
    assert.equal(text(highlightSegments('Йошкар-Ола', 'иош')), 'Йошкар-Ола');
    assert.equal(text(highlightSegments('Нижний Новгород', 'нижний нов')), '[Нижний Нов]город');
    assert.equal(text(highlightSegments('Нижний Новгород', 'новгород нижний')), '[Нижний] [Новгород]');
    assert.equal(text(highlightSegments('Ростов-на-Дону', 'дону')), 'Ростов-на-[Дону]');
    assert.equal(text(highlightSegments('Ростов-на-Дону', 'ростов на дону')), '[Ростов]-[на]-[Дону]');
});

test('highlighting prefers a word start over a match inside a word', () => {
    assert.equal(text(highlightSegments('Lanna Anna', 'anna')), 'Lanna [Anna]');
    assert.equal(text(highlightSegments('Москва', 'ква')), 'Мос[ква]', 'a match inside a word is still shown when it is the only one');
    assert.equal(text(highlightSegments('Москва', 'в')), 'Москва', 'a single letter is only highlighted at a word start');
});

test('highlighting applies to alternate names and leaves unmatched text alone', () => {
    assert.equal(text(highlightSegments('Москва', 'mosc')), 'Москва');
    assert.equal(text(highlightSegments('Moscow', 'mosc')), '[Mosc]ow');
    assert.deepEqual(highlightSegments('Москва', ''), [{ text: 'Москва', match: false }]);
    assert.deepEqual(highlightSegments('', 'мос'), []);
    assert.deepEqual(highlightSegments(null, 'мос'), []);
});

test('coordinates are recognised client-side while plain numbers and names are not', () => {
    const calls = [];
    const parse = value => {
        calls.push(value);
        const [lat, lon] = value.split(/[\s,;]+/).map(Number);
        return { getLatitude: () => lat, getLongitude: () => lon };
    };

    assert.deepEqual(parseCoordinateQuery(' 55.7522,  37.6156 ', parse), { lat: 55.7522, lon: 37.6156 });
    assert.deepEqual(parseCoordinateQuery('55,7522 37,6156', parse), { lat: 55.7522, lon: 37.6156 }, 'decimal commas');
    assert.deepEqual(parseCoordinateQuery('-33.86 151.2', parse), { lat: -33.86, lon: 151.2 });
    assert.equal(parseCoordinateQuery('91 10', parse), null, 'latitude out of range');
    assert.equal(parseCoordinateQuery('10 181', parse), null, 'longitude out of range');
    assert.deepEqual(calls, ['55.7522, 37.6156', '55.7522 37.6156', '-33.86 151.2', '91 10', '10 181']);

    calls.length = 0;
    for (const query of ['12', '#12', '# 12', '№12', '№ 12', 'Moscow', 'Москва 12', '12 Moscow', '', 'news']) {
        assert.equal(coordinateText(query), null, query);
        assert.equal(parseCoordinateQuery(query, parse), null, query);
    }
    assert.deepEqual(calls, [], 'the parser only sees coordinate-like input');

    assert.equal(coordinateText('55°45′N 37°37′E'), "55°45'N 37°37'E");
    assert.equal(coordinateText('55°45′21″ с.ш. 37°37′04″ в.д.'), `55°45'21" N 37°37'04" E`);
    assert.equal(parseCoordinateQuery('55.7 37.6', () => { throw new Error('Invalid'); }), null);
    assert.equal(parseCoordinateQuery('55.7 37.6', undefined), null);
});

test('coordinate queries work with the bundled coordinate parser', () => {
    const parse = value => new Coordinates(value);
    const close = (actual, expected) => {
        assert.ok(Math.abs(actual.lat - expected.lat) < 1e-4 && Math.abs(actual.lon - expected.lon) < 1e-4, JSON.stringify(actual));
    };

    close(parseCoordinateQuery('55.7522, 37.6156', parse), { lat: 55.7522, lon: 37.6156 });
    close(parseCoordinateQuery('55°45′N 37°37′E', parse), { lat: 55.75, lon: 37.61667 });
    close(parseCoordinateQuery(`55°45'21"N 37°37'04"E`, parse), { lat: 55.75583, lon: 37.61778 });
    close(parseCoordinateQuery('33.8688 S 151.2093 E', parse), { lat: -33.8688, lon: 151.2093 });
    close(parseCoordinateQuery('55,7522 37,6156', parse), { lat: 55.7522, lon: 37.6156 });
    assert.equal(parseCoordinateQuery('12', parse), null);
    assert.equal(parseCoordinateQuery('55.7522 37.6156 12', parse), null);
});

test('the debouncer waits for a pause but still runs during continuous typing', t => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const calls = [];
    const debouncer = createDebouncer(value => calls.push(value), { wait: 120, maxWait: 350 });

    debouncer.schedule('мо');
    t.mock.timers.tick(119);
    assert.deepEqual(calls, []);
    t.mock.timers.tick(1);
    assert.deepEqual(calls, ['мо']);
    assert.equal(debouncer.pending(), false);

    for (const [index, query] of ['н', 'ни', 'ниж', 'нижн'].entries()) {
        if (index) t.mock.timers.tick(100);
        debouncer.schedule(query);
    }
    assert.deepEqual(calls, ['мо'], 'still typing after 300ms');
    t.mock.timers.tick(50);
    assert.deepEqual(calls, ['мо', 'нижн'], 'maxWait streams the latest query');

    debouncer.schedule('нижни');
    assert.equal(debouncer.pending(), true);
    debouncer.flush();
    assert.deepEqual(calls, ['мо', 'нижн', 'нижни']);
    t.mock.timers.tick(1000);
    assert.equal(calls.length, 3, 'a flushed call does not run again');

    debouncer.schedule('x');
    debouncer.cancel();
    t.mock.timers.tick(1000);
    assert.equal(calls.length, 3);
    debouncer.flush();
    assert.equal(calls.length, 3);
});

test('the LRU cache keeps the most recently used entries', () => {
    const cache = new LruCache(3);
    cache.set('a', 1).set('b', 2).set('c', 3);
    assert.equal(cache.get('a'), 1);
    cache.set('d', 4);
    assert.equal(cache.has('b'), false, 'the least recently used entry is evicted');
    assert.deepEqual([...cache.entries.keys()], ['c', 'a', 'd']);
    cache.set('c', 30);
    cache.set('e', 5);
    assert.equal(cache.has('a'), false);
    assert.equal(cache.get('c'), 30);
    assert.equal(cache.size, 3);
    assert.equal(cache.get('missing'), undefined);
});

function memoryStorage() {
    const values = new Map();
    return {
        values,
        getItem: name => (values.has(name) ? values.get(name) : null),
        setItem: (name, value) => values.set(name, String(value)),
        removeItem: name => values.delete(name),
    };
}

const city = (id, name = `City ${id}`) => ({
    type: 'city', id, name, alt_name: null, context: 'Region, Country', sources_count: 3, sources_label: '3 water sources',
    url: `https://rodnik.test/cities/city-${id}`, center: [37.6, 55.7], bounds: null, zoom: 13,
});

test('recent places are deduplicated, capped and stored per locale', () => {
    let recents = [];
    for (let id = 1; id <= 7; id++) recents = addRecent(recents, city(id));
    assert.deepEqual(recents.map(item => item.id), [7, 6, 5, 4, 3, 2]);

    recents = addRecent(recents, { ...city(4), alt_name: 'Город 4' });
    assert.deepEqual(recents.map(item => item.id), [4, 7, 6, 5, 3, 2], 'an existing place moves to the top');
    assert.equal(recents[0].alt_name, null);

    recents = addRecent(recents, { ...city(4), type: 'region', bounds: [1, 2, 3, 4] });
    assert.deepEqual(recents.slice(0, 2).map(item => `${item.type}:${item.id}`), ['region:4', 'city:4'], 'type and id identify a place');

    const point = { type: 'coordinates', id: 'x', name: '55.75220, 37.61560', center: [37.6156, 55.7522] };
    recents = addRecent(recents, point);
    recents = addRecent(recents, { ...point, id: 'y', center: [37.615601, 55.752199] });
    assert.equal(recents.filter(item => item.type === 'coordinates').length, 1, 'coordinates are deduplicated by rounded value');
    assert.equal(addRecent(recents, { type: 'city', name: 'No center' }), recents);

    const storage = memoryStorage();
    writeRecents(storage, 'ru', recents);
    assert.deepEqual(readRecents(storage, 'ru'), recents);
    assert.deepEqual(readRecents(storage, 'en'), []);
    assert.ok(storage.values.has(recentStorageKey('ru')));
    writeRecents(storage, 'ru', []);
    assert.equal(storage.values.has('rodnik.mapSearch.recent.ru'), false);
});

test('recent places survive unavailable or corrupted storage', () => {
    const broken = { getItem() { throw new Error('denied'); }, setItem() { throw new Error('quota'); }, removeItem() { throw new Error('denied'); } };
    assert.deepEqual(readRecents(broken, 'en'), []);
    assert.doesNotThrow(() => writeRecents(broken, 'en', [city(1)]));
    assert.deepEqual(readRecents(null, 'en'), []);

    const storage = memoryStorage();
    storage.setItem(recentStorageKey('en'), '{not json');
    assert.deepEqual(readRecents(storage, 'en'), []);
    storage.setItem(recentStorageKey('en'), JSON.stringify([
        { ...city(1), url: 'javascript:alert(1)' },
        { type: 'planet', name: 'Mars', center: [0, 0] },
        { ...city(2), center: ['37', '55'] },
        city(3),
    ]));
    const recents = readRecents(storage, 'en');
    assert.deepEqual(recents.map(item => item.id), [1, 3]);
    assert.equal(recents[0].url, null, 'only http(s) and same-site links are kept');
});

test('plural lines follow Laravel forms for English and Russian', () => {
    assert.equal(choosePlural(':count result|:count results', 1, 'en'), '1 result');
    assert.equal(choosePlural(':count result|:count results', 8, 'en'), '8 results');
    const russian = ':count результат|:count результата|:count результатов';
    assert.deepEqual([1, 2, 5, 21, 0].map(count => choosePlural(russian, count, 'ru')),
        ['1 результат', '2 результата', '5 результатов', '21 результат', '0 результатов']);
    assert.equal(choosePlural('Searching', 3, 'ru'), 'Searching');
});

test('missing translations fall back to English', () => {
    const t = searchTranslations({ button: 'Поиск мест', types: { city: 'Населённый пункт' }, keys: null, close: '' });
    assert.equal(t.button, 'Поиск мест');
    assert.equal(t.types.city, 'Населённый пункт');
    assert.equal(t.types.region, 'Region');
    assert.equal(t.keys.navigate, 'to navigate');
    assert.equal(t.close, 'Close');
    assert.equal(t.groups.maps, 'Saved maps');
    assert.equal(searchTranslations(undefined).placeholder, 'Search places, water sources and maps');
});

const namedSpring = (id, name = `Родник ${id}`) => ({
    type: 'spring', group: 'springs', id, name, alt_name: null, context: 'Родник · Мытищи, Московская область, Россия',
    sources_count: null, sources_label: '2 отчёта', url: `https://rodnik.test/ru/${id}/`, center: [37.7, 55.9], bounds: null, zoom: null,
});
const savedMap = (id, name = `Карта ${id}`) => ({
    type: 'map', group: 'maps', id, name, alt_name: null, context: 'Андрей · 26 сент. 2026', sources_count: null, sources_label: null,
    url: `https://rodnik.test/ru/maps/map-${id}/`, center: [29.9, 36.5], bounds: null, zoom: 9.5,
});
const rows = results => results.map(result => `${result.group}:${result.id}`);

test('responses are validated and capped per group', () => {
    const response = normalizeSearchResponse({
        query: 'мос',
        corrected: '',
        results: [
            city(1),
            ...Array.from({ length: 9 }, (_, index) => city(index + 1)),
            { type: 'coordinates', name: 'x', center: [1, 2] },
            ...Array.from({ length: 7 }, (_, index) => namedSpring(100 + index)),
            ...Array.from({ length: 6 }, (_, index) => savedMap(200 + index)),
        ],
    }, 'мос');
    assert.deepEqual(rows(response.results), [
        'places:1', 'places:2', 'places:3', 'places:4', 'places:5', 'places:6',
        'springs:100', 'springs:101', 'springs:102', 'springs:103', 'springs:104',
        'maps:200', 'maps:201', 'maps:202', 'maps:203',
    ], 'duplicates are dropped before capping');
    assert.equal(response.results.length, resultLimit);
    assert.equal(response.corrected, null);
    assert.deepEqual(normalizeSearchResponse(null, 'q'), { query: 'q', corrected: null, results: [] });
});

test('each group stays in one piece and results claiming another group are dropped', () => {
    const response = normalizeSearchResponse({
        results: [
            namedSpring(1),
            city(2),
            { ...namedSpring(3), group: undefined },
            { ...city(4), group: 'springs' },
            { ...city(5), group: 'nowhere' },
            { ...savedMap(6), url: null },
            { ...savedMap(7), url: 'javascript:alert(1)' },
            { ...savedMap(8), center: null, zoom: null },
            { ...city(9), group: 'places' },
            { ...namedSpring(10), center: null },
        ],
    }, 'q');
    assert.deepEqual(rows(response.results), ['springs:1', 'springs:3', 'places:2', 'places:9', 'maps:8']);
    assert.equal(response.results[4].center, null, 'a saved map opens by its link, so it needs no center');
});

test('group headers sit above their rows while row numbers run through all groups', () => {
    const view = linkedViewResult('https://rodnik.test/ru/maps/map-7/#m=12/36.5/29.9');
    const labels = { places: 'Места', springs: 'Источники воды', maps: 'Сохранённые карты' };
    const sections = resultSections([view, namedSpring(1), namedSpring(2), city(3), savedMap(7)], labels);

    assert.deepEqual(sections.map(section => [section.label, section.entries.map(entry => entry.index)]),
        [[null, [0]], ['Источники воды', [1, 2]], ['Места', [3]], ['Сохранённые карты', [4]]]);
    assert.deepEqual(sections.map(section => section.headerId),
        ['map-search-group-0', 'map-search-group-1', 'map-search-group-2', 'map-search-group-3']);
    assert.equal(new Set(sections.map(section => section.key)).size, 4);
    assert.deepEqual(resultSections([], labels), []);
});

test('a map view in a pasted link is read like the map reads its own address', () => {
    assert.deepEqual(linkedViewResult(' https://rodnik.app/ru/maps/trip/#m=12.5/55.75/37.6&l=topo '), {
        type: 'coordinates', group: null, id: '55.75000,37.60000', name: '55.75000, 37.60000', alt_name: null, context: null,
        sources_count: null, sources_label: null, url: null, center: [37.6, 55.75], bounds: null, zoom: 12.5,
    });
    assert.deepEqual(linkedViewResult('#map=3/-33.86/151.2')?.center, [151.2, -33.86], 'the long parameter name');
    for (const query of ['#123', '№7', 'https://rodnik.app/maps/trip/', '#m=12/95/37.6', '#m=29/55/37', '#m=12/55', '#m=a/b/c', '']) {
        assert.equal(linkedViewResult(query), null, query);
    }
    assert.deepEqual(focusRequest(linkedViewResult('#m=12/55.75/37.6')), { center: [37.6, 55.75], zoom: 12, label: '55.75000, 37.60000', marker: false },
        'a view shows an area, so its center is not marked');
});

test('pasted links keep their case in the cache, as saved map addresses differ by it', () => {
    assert.notEqual(cacheKey('en', 'https://rodnik.app/maps/AbCd'), cacheKey('en', 'https://rodnik.app/maps/abcd'));
    assert.equal(cacheKey('en', ' https://rodnik.app/maps/AbCd'), cacheKey('en', 'https://rodnik.app/maps/AbCd'));
    assert.equal(cacheKey('ru', 'Родник'), cacheKey('ru', 'родник'));
});

test('recent water sources and saved maps are kept, older ones without groups too', () => {
    let recents = addRecent([], { ...savedMap(5), center: null, zoom: null });
    recents = addRecent(recents, namedSpring(7));
    recents = addRecent(recents, savedMap(5));
    assert.deepEqual(rows(recents), ['maps:5', 'springs:7'], 'a map chosen again moves to the top');

    const storage = memoryStorage();
    writeRecents(storage, 'ru', recents);
    assert.deepEqual(readRecents(storage, 'ru'), recents);

    storage.setItem(recentStorageKey('ru'), JSON.stringify([
        { ...city(1), group: undefined },
        { ...savedMap(2), url: null },
        { ...savedMap(3), group: 'places' },
        { ...namedSpring(4), group: undefined },
    ]));
    assert.deepEqual(rows(readRecents(storage, 'ru')), ['places:1', 'springs:4']);
});

test('selection targets depend on the place type', () => {
    assert.deepEqual(focusRequest(city(1, 'Москва')), { center: [37.6, 55.7], zoom: 13, label: 'Москва', marker: true });
    assert.deepEqual(focusRequest({ type: 'region', name: 'Крым', center: [34, 45], bounds: [32.5, 44.4, 36.6, 46.2] }),
        { bounds: [32.5, 44.4, 36.6, 46.2], center: [34, 45], label: 'Крым', marker: false });
    assert.deepEqual(focusRequest({ type: 'country', name: 'Fiji', center: [178, -17], bounds: null }),
        { center: [178, -17], zoom: 5, label: 'Fiji', marker: false });
    assert.deepEqual(focusRequest({ type: 'coordinates', name: '55.75220, 37.61560', center: [37.6156, 55.7522], zoom: null }),
        { center: [37.6156, 55.7522], zoom: null, label: '55.75220, 37.61560', marker: true });
});

test('point-sized bounds of a region or country are marked with a pin instead of fitted', () => {
    assert.deepEqual(focusRequest({ type: 'region', name: 'Icolo e Bengo', center: [13.5, -9.2], bounds: [13.68816, -9.10567, 13.68816, -9.10567] }),
        { center: [13.68816, -9.10567], zoom: 10, label: 'Icolo e Bengo', marker: true }, 'a box built from one settlement');
    assert.deepEqual(focusRequest({ type: 'country', name: 'Vatican', center: [12.45, 41.9], bounds: [12.4457, 41.9002, 12.4584, 41.9074] }),
        { center: [(12.4457 + 12.4584) / 2, (41.9002 + 41.9074) / 2], zoom: 10, label: 'Vatican', marker: true });
    const [longitude, latitude] = focusRequest({ type: 'region', name: 'Edge', center: [180, 0], bounds: [179.999, -0.005, 180.009, 0.005] }).center;
    assert.ok(Math.abs(longitude + 179.996) < 1e-9 && latitude === 0, 'the center of a tiny box past the antimeridian stays a valid longitude');
    assert.deepEqual(focusRequest({ type: 'region', name: 'Strip', center: [10, 50], bounds: [10, 49.8, 10.01, 50.2] }),
        { bounds: [10, 49.8, 10.01, 50.2], center: [10, 50], label: 'Strip', marker: false }, 'only narrow in one direction');
});

function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((done, fail) => { resolve = done; reject = fail; });
    return { promise, resolve, reject };
}

const jsonResponse = (payload, status = 200) => ({ ok: status >= 200 && status < 300, status, json: async () => payload });

function setupSearch(t, { locale = 'ru', storage = memoryStorage(), parseCoordinates, matchMedia } = {}) {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const previousWindow = globalThis.window;
    const focused = [];
    const visits = [];
    const opened = [];
    globalThis.window = Object.assign(new EventTarget(), {
        rodnikLocale: locale,
        rodnikPublicBaseUrl: locale === 'ru' ? 'https://rodnik.test/ru' : 'https://rodnik.test',
        rodnikMapTranslations: { search: { results_count: ':count результат|:count результата|:count результатов', no_results: 'Ничего: :query' } },
        rodnikMap: { focusPlace: request => focused.push(request) },
        rodnikNavigation: { visit: url => visits.push(url) },
        open: (...args) => opened.push(args),
        openedPhotoswipe: null,
        matchMedia,
    });
    t.after(() => { globalThis.window = previousWindow; });
    const windowListeners = [];
    const addListener = globalThis.window.addEventListener.bind(globalThis.window);
    globalThis.window.addEventListener = (type, handler, listenerOptions) => {
        windowListeners.push({ type, handler, capture: Boolean(typeof listenerOptions === 'object' ? listenerOptions?.capture : listenerOptions) });
        addListener(type, handler, listenerOptions);
    };

    const requests = [];
    const fetch = (url, options) => {
        const response = deferred();
        requests.push({ url, options, ...response });
        return response.promise;
    };
    const ui = mapSearch({
        fetch,
        storage,
        isMac: true,
        parseCoordinates: parseCoordinates ?? (value => new Coordinates(value)),
    });
    ui.init();
    t.after(() => ui.destroy());

    const type = query => {
        ui.query = query;
        ui.update();
    };
    const respond = async (index, results, extra = {}) => {
        requests[index].resolve(jsonResponse({ query: decodeURIComponent(requests[index].url.split('q=')[1]), corrected: null, results, ...extra }));
        await flush();
    };
    const keydown = properties => {
        const event = Object.assign(new Event('keydown', { cancelable: true }), { key: '', code: '', ...properties });
        ui.keydown(event);
        return event;
    };

    return { ui, requests, focused, visits, opened, storage, type, respond, keydown, windowListeners };
}

const moscow = { ...city(524901, 'Москва'), context: 'Москва, Россия', sources_label: '5 123 источника воды', zoom: 11, center: [37.6155, 55.7522] };
const moscowRegion = {
    type: 'region', id: 12, name: 'Московская область', alt_name: null, context: 'Россия', sources_count: 900, sources_label: '900 источников воды',
    url: 'https://rodnik.test/ru/regions/moscow-oblast', center: [37.5, 55.6], bounds: [35.1, 54.2, 40.2, 56.9], zoom: null,
};
const spring = {
    type: 'spring', id: 7, name: 'Родник у дороги', alt_name: null, context: 'Москва, Россия', sources_count: null, sources_label: null,
    url: 'https://rodnik.test/ru/7', center: [37.61, 55.75], bounds: null, zoom: null,
};

test('typing searches after the debounce and shows the response', async t => {
    const { ui, requests, type, respond } = setupSearch(t);

    type('мос');
    assert.equal(ui.loading, true);
    assert.equal(requests.length, 0);
    t.mock.timers.tick(120);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, 'https://rodnik.test/ru/search/places?q=%D0%BC%D0%BE%D1%81');
    assert.equal(requests[0].options.headers.Accept, 'application/json');

    assert.equal(ui.spinner, false);
    t.mock.timers.tick(150);
    assert.equal(ui.spinner, true, 'the spinner appears once a request has been pending for 150ms');

    await respond(0, [moscow, moscowRegion]);
    assert.equal(ui.spinner, false);
    assert.equal(ui.loading, false);
    assert.equal(ui.view, 'list');
    assert.deepEqual(ui.items.map(item => item.name), ['Москва', 'Московская область']);
    t.mock.timers.tick(announceDelay);
    assert.equal(ui.announcement, '2 результата');
    assert.equal(text(ui.segments('Московская область')), '[Мос]ковская область');
    assert.equal(ui.subtitle(ui.items[0]), 'Москва, Россия · 5 123 источника воды');
});

test('a newer query aborts the older request and late responses are ignored', async t => {
    const { ui, requests, type, respond } = setupSearch(t);

    type('мо');
    t.mock.timers.tick(120);
    type('мос');
    t.mock.timers.tick(120);
    assert.equal(requests.length, 2);
    assert.equal(requests[0].options.signal.aborted, true);
    assert.equal(requests[1].options.signal.aborted, false);

    await respond(1, [moscow]);
    await respond(0, [moscowRegion]);
    assert.equal(ui.resultsFor, 'мос');
    assert.deepEqual(ui.items.map(item => item.id), [524901]);
});

test('older results stay visible while the next query loads', async t => {
    const { ui, requests, type, respond } = setupSearch(t);

    type('мос');
    t.mock.timers.tick(120);
    await respond(0, [moscow]);
    type('моск');
    assert.equal(ui.view, 'list');
    assert.deepEqual(ui.items.map(item => item.id), [524901]);
    assert.equal(text(ui.segments('Москва')), '[Мос]ква', 'highlighting follows the visible results');
    t.mock.timers.tick(120);
    await respond(1, []);
    assert.equal(ui.view, 'empty');
    t.mock.timers.tick(announceDelay);
    assert.equal(ui.announcement, 'Ничего: моск');
    assert.equal(requests.length, 2);
});

test('a cached query renders synchronously without a request', async t => {
    const { ui, requests, type, respond } = setupSearch(t);

    type('мос');
    t.mock.timers.tick(120);
    await respond(0, [moscow]);
    type('мосу');
    t.mock.timers.tick(120);
    await respond(1, []);

    type('  МОС ');
    assert.equal(ui.loading, false);
    assert.equal(ui.resultsFor, 'МОС');
    assert.deepEqual(ui.items.map(item => item.id), [524901]);
    t.mock.timers.tick(1000);
    assert.equal(requests.length, 2);
});

test('typing past a pending request streams its results without interrupting the debounce', async t => {
    const { ui, requests, type, respond } = setupSearch(t);

    type('мо');
    t.mock.timers.tick(120);
    type('мос');
    await respond(0, [moscowRegion]);
    assert.equal(ui.resultsFor, 'мо');
    assert.equal(ui.loading, true, 'the newer query is still scheduled');
    t.mock.timers.tick(120);
    assert.equal(requests.length, 2);
});

test('Enter before results arrive goes to the first result as soon as it arrives', async t => {
    const { ui, requests, focused, storage, type, respond, keydown } = setupSearch(t);
    ui.isOpen = true;

    type('моск');
    const event = keydown({ key: 'Enter' });
    assert.equal(event.defaultPrevented, true);
    assert.equal(requests.length, 1, 'Enter skips the remaining debounce');
    assert.deepEqual(focused, []);

    await respond(0, [moscow, moscowRegion]);
    assert.deepEqual(focused, [{ center: [37.6155, 55.7522], zoom: 11, label: 'Москва', marker: true }]);
    assert.equal(ui.isOpen, false);
    assert.deepEqual(readRecents(storage, 'ru').map(item => item.id), [524901]);
});

test('a pending Enter is dropped when the query changes before results arrive', async t => {
    const { ui, requests, focused, type, respond, keydown } = setupSearch(t);

    type('моск');
    keydown({ key: 'Enter' });
    type('москв');
    await respond(0, [moscow]);
    assert.deepEqual(focused, []);
    t.mock.timers.tick(120);
    await respond(1, [moscow]);
    assert.deepEqual(focused, []);
    assert.equal(requests.length, 2);
});

test('Enter during IME composition does nothing', t => {
    const { requests, type, keydown } = setupSearch(t);
    type('моск');
    const event = keydown({ key: 'Enter', isComposing: true });
    assert.equal(event.defaultPrevented, false);
    assert.equal(requests.length, 0);
});

test('arrow keys move the active result and Enter selects it', async t => {
    const { ui, focused, visits, type, respond, keydown } = setupSearch(t);

    type('мос');
    t.mock.timers.tick(120);
    await respond(0, [moscow, moscowRegion, spring]);
    keydown({ key: 'ArrowUp' });
    assert.equal(ui.activeIndex, 2, 'moving up from the first result wraps');
    keydown({ key: 'ArrowDown' });
    assert.equal(ui.activeIndex, 0);
    keydown({ key: 'n', ctrlKey: true });
    assert.equal(ui.activeIndex, 1);
    keydown({ key: 'Enter' });
    assert.deepEqual(focused, [{ bounds: [35.1, 54.2, 40.2, 56.9], center: [37.5, 55.6], label: 'Московская область', marker: false }]);

    type('мо');
    type('мос');
    keydown({ key: 'ArrowDown' });
    keydown({ key: 'ArrowDown' });
    keydown({ key: 'Enter' });
    assert.deepEqual(visits, ['https://rodnik.test/ru/7'], 'a water source opens its page');
    assert.equal(focused.length, 1);
});

test('Cmd+Enter opens the active result page in a new tab', async t => {
    const { ui, opened, focused, type, respond, keydown } = setupSearch(t);
    ui.isOpen = true;

    type('мос');
    t.mock.timers.tick(120);
    await respond(0, [moscow]);
    keydown({ key: 'Enter', metaKey: true });
    assert.deepEqual(opened, [['https://rodnik.test/cities/city-524901', '_blank', 'noopener']]);
    assert.deepEqual(focused, []);
    assert.equal(ui.isOpen, true);
});

test('mouse movement selects a row but a resting pointer does not', t => {
    const { ui } = setupSearch(t);
    ui.hover(2, { clientX: 10, clientY: 20 });
    assert.equal(ui.activeIndex, 2);
    ui.activeIndex = 0;
    ui.hover(1, { clientX: 10, clientY: 20 });
    assert.equal(ui.activeIndex, 0, 'content scrolled under the same pointer position');
    ui.hover(1, { clientX: 10, clientY: 24 });
    assert.equal(ui.activeIndex, 1);
});

test('coordinates are offered instantly without a request', t => {
    const { ui, requests, focused, type, keydown } = setupSearch(t);

    type('55.7522, 37.6156');
    assert.equal(ui.view, 'list');
    assert.equal(ui.items.length, 1);
    assert.equal(ui.items[0].name, '55.75220, 37.61560');
    assert.equal(ui.subtitle(ui.items[0]), 'Go to coordinates');
    keydown({ key: 'Enter' });
    assert.deepEqual(focused, [{ center: [37.6156, 55.7522], zoom: null, label: '55.75220, 37.61560', marker: true }]);
    t.mock.timers.tick(1000);
    assert.equal(requests.length, 0);
});

test('short queries show recent places without searching', t => {
    const storage = memoryStorage();
    writeRecents(storage, 'ru', [moscow]);
    const { ui, requests, type } = setupSearch(t, { storage });

    assert.equal(ui.view, 'recent');
    type('м');
    assert.equal(ui.view, 'recent');
    assert.equal(ui.icon(ui.items[0]), 'recent');
    t.mock.timers.tick(1000);
    assert.equal(requests.length, 0);

    ui.clearRecents();
    assert.equal(ui.view, 'hint');
    assert.equal(storage.values.size, 0);
});

test('failed and rate-limited searches can be retried', async t => {
    const { ui, requests, type } = setupSearch(t);

    type('мос');
    t.mock.timers.tick(120);
    requests[0].resolve(jsonResponse({ message: 'Too Many Attempts.' }, 429));
    await flush();
    assert.equal(ui.view, 'error');
    assert.equal(ui.error, 'rate_limited');
    assert.deepEqual(ui.items, []);

    ui.retry();
    assert.equal(requests.length, 2);
    requests[1].reject(new TypeError('Failed to fetch'));
    await flush();
    assert.equal(ui.error, 'error');

    ui.retry();
    requests[2].resolve(jsonResponse({ query: 'мос', corrected: null, results: [moscow] }));
    await flush();
    assert.equal(ui.view, 'list');
});

test('a layout-corrected response is shown with its corrected query', async t => {
    const { ui, type, respond } = setupSearch(t);

    type('vjc');
    t.mock.timers.tick(120);
    await respond(0, [moscow], { corrected: 'мос' });
    assert.equal(ui.corrected, 'мос');
    assert.equal(text(ui.segments('Москва')), '[Мос]ква');
});

test('changing the page language refreshes copy, recents and cached results', async t => {
    const storage = memoryStorage();
    writeRecents(storage, 'en', [{ ...moscow, name: 'Moscow' }]);
    const { ui, requests, type, respond } = setupSearch(t, { storage });

    type('мос');
    t.mock.timers.tick(120);
    await respond(0, [moscow]);

    Object.assign(window, {
        rodnikLocale: 'en',
        rodnikPublicBaseUrl: 'https://rodnik.test',
        rodnikMapTranslations: { search: { button: 'Find places' } },
    });
    window.dispatchEvent(new Event('rodnik:navigated'));
    assert.equal(ui.t.button, 'Find places');
    assert.equal(ui.t.results_count, ':count result|:count results');
    assert.deepEqual(ui.recents.map(item => item.name), ['Moscow']);
    assert.deepEqual(ui.results, []);

    ui.isOpen = false;
    ui.update();
    t.mock.timers.tick(120);
    assert.equal(requests.length, 2, 'the same query is searched again in the new language');
    assert.equal(requests[1].url, 'https://rodnik.test/search/places?q=%D0%BC%D0%BE%D1%81');
});

function dialogRefs() {
    const calls = [];
    // Browsers fire the close event a task after the dialog closes.
    const closeEvents = [];
    const dialog = {
        open: false,
        showModal() {
            if (this.open) throw new Error('InvalidStateError');
            this.open = true;
            calls.push('showModal');
        },
        close() { this.open = false; calls.push('close'); closeEvents.push('close'); },
        contains: () => false,
    };
    const input = { focus() { calls.push('focus'); }, select() { calls.push('select'); } };
    return { calls, closeEvents, refs: { dialog, input, panel: { contains: target => target === 'panel' } } };
}

test('hotkeys open the dialog synchronously and are removed on destroy', t => {
    const { ui } = setupSearch(t);
    const { calls, refs } = dialogRefs();
    ui.$refs = refs;
    const press = properties => {
        const event = Object.assign(new Event('keydown', { cancelable: true }), { key: '', code: '', ...properties });
        window.dispatchEvent(event);
        return event;
    };

    const slash = press({ key: '/', code: 'Slash' });
    assert.equal(slash.defaultPrevented, true);
    assert.equal(ui.isOpen, true);
    assert.deepEqual(calls, ['showModal', 'focus', 'select'], 'the input is focused within the key handler');

    const toggle = press({ key: 'k', code: 'KeyK', metaKey: true });
    assert.equal(toggle.defaultPrevented, true);
    assert.equal(ui.isOpen, false);

    window.openedPhotoswipe = {};
    assert.equal(press({ key: '/', code: 'Slash' }).defaultPrevented, false);
    assert.equal(ui.isOpen, false, 'a photo viewer keeps the keyboard');
    window.openedPhotoswipe = null;

    press({ key: 'л', code: 'KeyK', metaKey: true });
    assert.equal(ui.isOpen, true);
    refs.dialog.open = false;
    press({ key: 'k', code: 'KeyK', metaKey: true });
    assert.equal(ui.isOpen, true, 'a dialog closed by Escape reopens before its close event arrives');
    assert.equal(refs.dialog.open, true);
    ui.close();

    ui.destroy();
    assert.equal(press({ key: '/', code: 'Slash' }).defaultPrevented, false);
    assert.equal(ui.isOpen, false);
});

test('searches opened over a zoomed-in map send its view, and a minimized map sends none', async t => {
    const { ui, requests, type, respond } = setupSearch(t);
    const { refs } = dialogRefs();
    ui.$refs = refs;
    window.rodnikMap.getLayout = () => ({ minimized: false });
    window.rodnikMap.getViewportBounds = () => ({ west: 37.1, south: 55.5, east: 38.2, north: 56.0 });

    ui.open();
    type('алекс');
    t.mock.timers.tick(120);
    assert.equal(requests.at(-1).url, 'https://rodnik.test/ru/search/places?q=%D0%B0%D0%BB%D0%B5%D0%BA%D1%81&bbox=37.1,55.5,38.2,56');
    await respond(requests.length - 1, [moscow]);
    ui.close();

    window.rodnikMap.getLayout = () => ({ minimized: true });
    ui.open();
    type('алекс');
    t.mock.timers.tick(120);
    assert.equal(requests.at(-1).url, 'https://rodnik.test/ru/search/places?q=%D0%B0%D0%BB%D0%B5%D0%BA%D1%81',
        'the cached result for the other view is not reused');
    ui.close();
});

test('clicking the backdrop closes but a drag that ends outside the panel does not', t => {
    const { ui } = setupSearch(t);
    const { refs } = dialogRefs();
    ui.$refs = refs;
    ui.open();

    ui.pointerDown({ target: 'panel' });
    ui.backdropClick({ target: 'backdrop' });
    assert.equal(ui.isOpen, true);

    ui.pointerDown({ target: 'backdrop' });
    ui.backdropClick({ target: 'backdrop' });
    assert.equal(ui.isOpen, false);
});

test('a spelling the database tells apart is searched even after its look-alike', async t => {
    const { ui, requests, type, respond } = setupSearch(t, { locale: 'en' });
    const wroclaw = { ...city(3081368, 'Wrocław'), context: 'Lower Silesia, Poland' };

    type('Wroclaw');
    t.mock.timers.tick(120);
    await respond(0, []);
    assert.equal(ui.view, 'empty');

    type('Wrocław');
    assert.equal(ui.loading, true, 'the empty result for "Wroclaw" is not reused');
    t.mock.timers.tick(120);
    assert.equal(requests.length, 2);
    assert.equal(requests[1].url, 'https://rodnik.test/search/places?q=Wroc%C5%82aw');
    await respond(1, [wroclaw]);
    assert.deepEqual(ui.items.map(item => item.name), ['Wrocław']);

    type('Lodz');
    t.mock.timers.tick(120);
    type('Łódź');
    t.mock.timers.tick(120);
    assert.equal(requests.length, 4, 'a look-alike still on its way does not stand in either');
    assert.equal(requests[2].options.signal.aborted, true);
});

test('Enter on a row picked from the list still on screen selects it without waiting', async t => {
    const { ui, requests, focused, type, respond, keydown } = setupSearch(t);
    const mosalsk = { ...city(527012, 'Мосальск'), center: [34.99, 54.49], zoom: 13 };
    ui.isOpen = true;

    type('мос');
    t.mock.timers.tick(120);
    await respond(0, [moscow, moscowRegion, mosalsk]);
    type('моск');
    keydown({ key: 'ArrowDown' });
    keydown({ key: 'ArrowDown' });
    assert.equal(ui.activeId, 'map-search-option-2');
    keydown({ key: 'Enter' });
    assert.deepEqual(focused, [focusRequest(mosalsk)], 'the highlighted row, not the first result of "моск"');
    assert.equal(ui.isOpen, false);

    t.mock.timers.tick(120);
    await respond(requests.length - 1, [moscow]);
    assert.equal(focused.length, 1, 'the results arriving later do not select again');
});

test('a row picked with the mouse or among recent places is selected before results arrive', async t => {
    const storage = memoryStorage();
    writeRecents(storage, 'ru', [moscow, moscowRegion]);
    const { ui, requests, focused, type, keydown } = setupSearch(t, { storage });

    type('мо');
    assert.equal(ui.view, 'recent', 'recent places stay on screen while the first search loads');
    keydown({ key: 'ArrowDown' });
    keydown({ key: 'Enter' });
    assert.deepEqual(focused.map(request => request.label), ['Московская область']);

    type('м');
    type('мос');
    assert.deepEqual(ui.items.map(item => item.name), ['Московская область', 'Москва'], 'the chosen place moved to the top');
    ui.hover(1, { clientX: 5, clientY: 50 });
    keydown({ key: 'Enter' });
    assert.deepEqual(focused.map(request => request.label), ['Московская область', 'Москва']);
    assert.equal(requests.length, 0);
});

test('Enter without a picked row waits for the new results even while older ones are shown', async t => {
    const { ui, requests, focused, type, respond, keydown } = setupSearch(t);

    type('мос');
    t.mock.timers.tick(120);
    await respond(0, [moscow, moscowRegion]);
    type('моск');
    keydown({ key: 'ArrowDown' });
    type('москов');
    keydown({ key: 'Enter' });
    assert.deepEqual(focused, [], 'typing after moving starts over');
    assert.equal(requests.length, 2);

    await respond(1, [moscowRegion, moscow]);
    assert.deepEqual(focused.map(request => request.label), ['Московская область']);
    assert.equal(ui.resultsFor, 'москов');
});

test('each result count is announced again even when the text repeats', async t => {
    const { ui, type, respond } = setupSearch(t);
    const spoken = [];
    let announcement = ui.announcement;
    Object.defineProperty(ui, 'announcement', {
        get: () => announcement,
        set: value => { announcement = value; spoken.push(value); },
    });

    type('мос');
    t.mock.timers.tick(120);
    await respond(0, [moscow, moscowRegion]);
    assert.equal(ui.announcement, '', 'the live region is emptied first');
    t.mock.timers.tick(announceDelay);
    assert.equal(ui.announcement, '2 результата');

    type('моск');
    t.mock.timers.tick(120);
    await respond(1, [moscow, moscowRegion]);
    t.mock.timers.tick(announceDelay);
    assert.deepEqual(spoken, ['', '2 результата', '', '2 результата']);

    type('55.7522, 37.6156');
    type('мос');
    t.mock.timers.tick(announceDelay);
    assert.deepEqual(spoken.slice(4), ['', '', '2 результата'], 'only the latest of quick announcements is spoken');
});

test('a late close event does not close a dialog that was opened again', t => {
    const { ui } = setupSearch(t);
    const { refs, closeEvents } = dialogRefs();
    ui.$refs = refs;
    // @close="onClose()"
    const deliverCloseEvents = () => closeEvents.splice(0).forEach(() => ui.onClose());
    const press = properties => window.dispatchEvent(Object.assign(new Event('keydown', { cancelable: true }), { key: '', code: '', ...properties }));
    const trigger = { isConnected: true, focused: 0, focus() { this.focused++; } };

    ui.open(trigger);
    refs.dialog.open = false;
    closeEvents.push('close');
    press({ key: 'k', code: 'KeyK', metaKey: true });
    assert.equal(ui.isOpen, true, 'Escape and then Cmd+K before the close event');
    deliverCloseEvents();
    assert.equal(ui.isOpen, true);
    assert.equal(refs.dialog.open, true);
    assert.equal(trigger.focused, 1, 'focus went back once, for the Escape');

    press({ key: 'k', code: 'KeyK', metaKey: true });
    assert.equal(ui.isOpen, false, 'Cmd+K still toggles the reopened dialog closed');
    assert.equal(refs.dialog.open, false);
    deliverCloseEvents();
    assert.equal(ui.isOpen, false);

    refs.dialog.open = true;
    ui.open();
    assert.equal(ui.isOpen, true, 'a dialog left open without a modal is reset and opened as one');
    assert.equal(closeEvents.length, 1);
    deliverCloseEvents();
    assert.equal(ui.isOpen, true);
    assert.equal(refs.dialog.open, true);
});

test('keys pressed in the dialog do not reach the map behind it, while hotkeys still work', t => {
    const { ui, windowListeners } = setupSearch(t);
    const { calls, refs } = dialogRefs();
    ui.$refs = refs;
    const previousFrame = globalThis.requestAnimationFrame;
    globalThis.requestAnimationFrame = () => 0;
    t.after(() => { globalThis.requestAnimationFrame = previousFrame; });

    // OpenLayers' default keyboard pan listens on the map element (#map), which contains the dialog.
    const view = new View({ center: fromLonLat([37.6, 55.7]), zoom: 10 });
    const pan = new KeyboardPan({ duration: 0 });
    const olMap = { getView: () => view };
    const mapElement = event => pan.handleEvent({ type: 'keydown', originalEvent: event, map: olMap });
    const keyCodes = { ArrowDown: 40, ArrowUp: 38 };
    const press = (properties, { insideDialog = true } = {}) => {
        const event = {
            type: 'keydown', key: '', code: '', ctrlKey: false, metaKey: false, altKey: false, shiftKey: false,
            target: { tagName: 'BUTTON' }, keyCode: keyCodes[properties.key] ?? 0, defaultPrevented: false, stopped: false,
            preventDefault() { this.defaultPrevented = true; },
            stopPropagation() { this.stopped = true; },
            ...properties,
        };
        const window = phase => windowListeners.filter(listener => listener.type === 'keydown' && listener.capture === (phase === 'capture'))
            .map(listener => listener.handler);
        const path = [
            ...window('capture'),
            // The dialog's @keydown.stop.
            ...(insideDialog ? [dialogEvent => dialogEvent.stopPropagation()] : []),
            mapElement,
            ...window('bubble'),
        ];
        for (const handler of path) {
            handler(event);
            if (event.stopped) break;
        }
        return event;
    };
    const center = () => toLonLat(view.getCenter()).map(value => value.toFixed(6));
    const start = center();

    press({ key: 'ArrowDown' }, { insideDialog: false });
    assert.notDeepEqual(center(), start, 'arrow keys on a map button pan the map');
    const moved = center();

    ui.open();
    press({ key: 'ArrowDown' });
    press({ key: 'ArrowUp' });
    assert.deepEqual(center(), moved, 'arrow keys on the clear or esc button leave the map alone');

    calls.length = 0;
    const slash = press({ key: '/', code: 'Slash' });
    assert.equal(slash.defaultPrevented, true);
    assert.deepEqual(calls, ['focus', 'select'], '"/" on a dialog button returns to the input');
    press({ key: 'k', code: 'KeyK', metaKey: true });
    assert.equal(ui.isOpen, false, 'Cmd+K pressed inside the dialog closes it');
});

test('choosing a water source clears the previous search marker before leaving', async t => {
    const { type, respond, keydown } = setupSearch(t);
    const events = [];
    window.rodnikMap.setSearchMarker = center => events.push(['marker', center]);
    window.rodnikNavigation.visit = url => events.push(['visit', url]);

    type('№7');
    t.mock.timers.tick(120);
    await respond(0, [spring]);
    keydown({ key: 'Enter' });
    assert.deepEqual(events, [['marker', null], ['visit', 'https://rodnik.test/ru/7']]);
});

test('choosing a saved map clears the search marker and opens the map', async t => {
    const { ui, type, respond, keydown, opened, storage } = setupSearch(t);
    const events = [];
    window.rodnikMap.setSearchMarker = center => events.push(['marker', center]);
    window.rodnikNavigation.visit = url => events.push(['visit', url]);
    ui.isOpen = true;

    type('ликийск');
    t.mock.timers.tick(120);
    await respond(0, [savedMap(5, 'Ликийская тропа'), namedSpring(7)]);
    assert.deepEqual(ui.sections.map(section => section.label), ['Saved maps', 'Water sources']);
    assert.equal(ui.subtitle(ui.items[0]), 'Андрей · 26 сент. 2026');

    keydown({ key: 'Enter', metaKey: true });
    assert.deepEqual(opened, [['https://rodnik.test/ru/maps/map-5/', '_blank', 'noopener']], 'Cmd+Enter opens the map in a new tab');
    assert.equal(ui.isOpen, true);

    keydown({ key: 'ArrowDown' });
    keydown({ key: 'Enter', metaKey: true });
    assert.deepEqual(opened.at(-1), ['https://rodnik.test/ru/7/', '_blank', 'noopener'], 'and a water source found by name');

    keydown({ key: 'ArrowUp' });
    keydown({ key: 'Enter' });
    assert.deepEqual(events, [['marker', null], ['visit', 'https://rodnik.test/ru/maps/map-5/']]);
    assert.equal(ui.isOpen, false);
    assert.deepEqual(rows(readRecents(storage, 'ru')).slice(0, 2), ['maps:5', 'springs:7']);
});

test('without page navigation a saved map opens with a full page load', async t => {
    const { type, respond, keydown } = setupSearch(t);
    const assigned = [];
    window.rodnikNavigation = undefined;
    window.location = { assign: url => assigned.push(url) };

    type('ликийск');
    t.mock.timers.tick(120);
    await respond(0, [savedMap(5)]);
    keydown({ key: 'Enter' });
    assert.deepEqual(assigned, ['https://rodnik.test/ru/maps/map-5/']);
});

test('a pasted link offers its map view first while the page it points to is looked up', async t => {
    const { ui, requests, focused, type, respond, keydown } = setupSearch(t);
    const link = 'https://rodnik.test/ru/maps/map-5/#m=12/36.5/29.9&l=topo';

    type(link);
    assert.equal(ui.view, 'list');
    assert.deepEqual(ui.items.map(item => item.name), ['36.50000, 29.90000']);
    assert.equal(ui.subtitle(ui.items[0]), 'Map view from the link');
    t.mock.timers.tick(120);
    assert.equal(requests.length, 1, 'the link is still looked up');
    assert.equal(decodeURIComponent(requests[0].url.split('q=')[1]), link);

    await respond(0, [savedMap(5)]);
    assert.deepEqual(rows(ui.items), ['null:36.50000,29.90000', 'maps:5']);
    assert.deepEqual(ui.sections.map(section => section.label), [null, 'Saved maps']);
    t.mock.timers.tick(announceDelay);
    assert.equal(ui.announcement, '2 результата');

    keydown({ key: 'Enter' });
    assert.deepEqual(focused, [{ center: [29.9, 36.5], zoom: 12, label: '36.50000, 29.90000', marker: false }]);
});

test('Enter on a pasted link goes to its view without waiting for the lookup', t => {
    const { requests, focused, type, keydown } = setupSearch(t);

    type('https://rodnik.test/#m=9/55.75/37.6');
    keydown({ key: 'Enter' });
    assert.deepEqual(focused, [{ center: [37.6, 55.75], zoom: 9, label: '55.75000, 37.60000', marker: false }]);
    assert.equal(requests.length, 0);
});

test('a map view alone is not looked up, and a failed lookup keeps the view', async t => {
    const { ui, requests, type } = setupSearch(t);

    type('#m=10/55.75/37.6');
    t.mock.timers.tick(1000);
    assert.equal(requests.length, 0);
    assert.deepEqual(ui.items.map(item => item.zoom), [10]);

    type('rodnik.test/maps/gone/#m=10/55.75/37.6');
    t.mock.timers.tick(120);
    requests[0].reject(new TypeError('Failed to fetch'));
    await flush();
    assert.equal(ui.error, null);
    assert.equal(ui.view, 'list');
    assert.deepEqual(ui.items.map(item => item.zoom), [10]);
});

test('the phone sheet shows a shorter placeholder', t => {
    const media = Object.assign(new EventTarget(), { matches: true });
    const queries = [];
    const { ui } = setupSearch(t, { matchMedia: query => { queries.push(query); return media; } });

    assert.deepEqual(queries, ['(max-width: 639.98px)']);
    assert.equal(ui.placeholder, 'Places, water sources, maps');
    media.dispatchEvent(Object.assign(new Event('change'), { matches: false }));
    assert.equal(ui.placeholder, 'Search places, water sources and maps', 'a window widened past the breakpoint');
});

test('recent places are listed without group headers', t => {
    const storage = memoryStorage();
    writeRecents(storage, 'ru', [savedMap(5), moscow, namedSpring(7)]);
    const { ui } = setupSearch(t, { storage });

    assert.equal(ui.view, 'recent');
    assert.deepEqual(ui.sections.map(section => [section.label, section.entries.map(entry => entry.index)]), [[null, [0, 1, 2]]]);
    assert.equal(ui.icon(ui.items[0]), 'recent');
});

function placeMap({ minimized = false, width = 1000, log = [] } = {}) {
    const layout = { fullscreen: false, minimized };
    const view = new View({ center: fromLonLat([10, 50]), zoom: 6 });
    const animations = [];
    const fits = [];
    view.animate = animation => { log.push('animate'); animations.push(animation); };
    view.fit = (extent, options) => { log.push('fit'); fits.push({ extent, options }); };
    const map = Object.assign(Object.create(OpenLayersMap.prototype), {
        disposed: false,
        view,
        map: { getSize: () => [width, 700], updateSize: () => log.push('updateSize') },
        searchMarkerLayer: new VectorLayer({ source: new VectorSource() }),
        getLayout: () => layout,
        notifySharedStateChange: () => log.push('layout'),
    });
    return { map, layout, animations, fits, log, markers: () => map.searchMarkerLayer.getSource().getFeatures() };
}

function withAlpine(t) {
    const previous = globalThis.Alpine;
    globalThis.Alpine = { nextTick: () => Promise.resolve() };
    t.after(() => { globalThis.Alpine = previous; });
}

test('focusing a city flies to it and labels it with a marker', async t => {
    withAlpine(t);
    const { map, animations, markers } = placeMap();

    await map.focusPlace({ center: [37.6155, 55.7522], zoom: 11, label: 'Москва', marker: true });
    assert.deepEqual(animations, [{ center: fromLonLat([37.6155, 55.7522]), zoom: 11, duration: 400 }]);
    assert.equal(markers().length, 1);
    assert.equal(markers()[0].get('label'), 'Москва');
    assert.deepEqual(markers()[0].getGeometry().getCoordinates(), fromLonLat([37.6155, 55.7522]));

    map.view.setZoom(16);
    await map.focusPlace({ center: [30, 60], label: '60.00000, 30.00000', marker: true });
    assert.equal(animations[1].zoom, 16, 'a place without a zoom never zooms out from a closer view');
    map.view.setZoom(5);
    await map.focusPlace({ center: [30, 60], marker: true });
    assert.equal(animations[2].zoom, 13);
    assert.equal(markers().length, 1, 'only the latest place is marked');
});

test('focusing a region fits its bounds and clears the previous marker', async t => {
    withAlpine(t);
    const { map, fits, markers } = placeMap();
    await map.focusPlace({ center: [1, 2], zoom: 12, label: 'Town', marker: true });

    await map.focusPlace({ bounds: [35.1, 54.2, 40.2, 56.9], center: [37.65, 55.55], label: 'Московская область', marker: false });
    assert.equal(markers().length, 0);
    assert.equal(fits.length, 1);
    assert.deepEqual(fits[0].extent, transformExtent([35.1, 54.2, 40.2, 56.9], 'EPSG:4326', 'EPSG:3857'));
    assert.deepEqual(fits[0].options, { size: [1000, 700], padding: [48, 48, 48, 48], maxZoom: 12, duration: 400 });

    const narrow = placeMap({ width: 375 });
    await narrow.map.focusPlace({ bounds: [19.6, 41.2, 190.9, 81.9], center: [105.25, 61.55], marker: false });
    assert.deepEqual(narrow.fits[0].options.padding, [24, 24, 24, 24]);
    assert.deepEqual(narrow.fits[0].extent, transformExtent([19.6, 41.2, 180, 81.9], 'EPSG:4326', 'EPSG:3857'),
        'a box across the antimeridian is cut at it');
});

test('focusing a place expands a minimized map before fitting', async t => {
    withAlpine(t);
    const { map, layout, log } = placeMap({ minimized: true });

    const focus = map.focusPlace({ bounds: [35.1, 54.2, 40.2, 56.9], center: [37.65, 55.55], marker: false });
    assert.equal(layout.minimized, false);
    assert.deepEqual(log, ['layout'], 'the fit waits for the expanded size');
    await focus;
    assert.deepEqual(log, ['layout', 'updateSize', 'fit']);
});

test('focusing a place respects reduced motion and a disposed map', async t => {
    withAlpine(t);
    const previousMatchMedia = globalThis.matchMedia;
    globalThis.matchMedia = query => ({ matches: query === '(prefers-reduced-motion: reduce)' });
    t.after(() => { globalThis.matchMedia = previousMatchMedia; });

    const { map, animations, log } = placeMap();
    await map.focusPlace({ center: [37.6, 55.7], zoom: 11, marker: true });
    assert.equal(animations[0].duration, 0);

    map.disposed = true;
    await map.focusPlace({ center: [37.6, 55.7], zoom: 11, marker: true });
    assert.equal(log.length, 1);
});

test('the search marker clears without a marker layer', () => {
    const map = Object.create(OpenLayersMap.prototype);
    assert.doesNotThrow(() => map.setSearchMarker([1, 2], 'x'));
});

test('a place across the antimeridian is fitted on the side holding most of it', async t => {
    withAlpine(t);
    const previous = { matchMedia: globalThis.matchMedia, requestAnimationFrame: globalThis.requestAnimationFrame };
    globalThis.matchMedia = query => ({ matches: query === '(prefers-reduced-motion: reduce)' });
    globalThis.requestAnimationFrame = () => 0;
    t.after(() => Object.assign(globalThis, previous));
    const size = [720, 900];
    const fitted = async bounds => {
        const view = createMapView({ center: fromLonLat([10, 50]), zoom: 3 });
        view.setViewportSize(size);
        const map = Object.assign(Object.create(OpenLayersMap.prototype), {
            disposed: false,
            view,
            map: { getSize: () => size },
            getLayout: () => ({ minimized: false }),
        });
        await map.focusPlace({ bounds, center: [0, 0], marker: false });
        const [west, south, east, north] = transformExtent(view.calculateExtent(size), 'EPSG:3857', 'EPSG:4326');
        return { west, south, east, north, zoom: view.getZoom(), center: toLonLat(view.getCenter()) };
    };
    const withinWorld = place => place.west >= -180 - 1e-6 && place.east <= 180 + 1e-6 && Math.abs(place.center[0]) <= 180;

    const chukotka = await fitted([157.69, 61.81, 190.36, 71.6]);
    assert.ok(withinWorld(chukotka), JSON.stringify(chukotka));
    assert.ok(chukotka.west <= 157.69 && chukotka.east >= 179.99, 'the larger west part is shown whole');
    assert.ok(chukotka.south <= 61.81 && chukotka.north >= 71.6);
    assert.ok(chukotka.zoom > 5, `zoomed to the shown part, not the whole box (${chukotka.zoom})`);

    const eastward = await fitted([178.5, -19.2, 185.2, -16.1]);
    assert.ok(withinWorld(eastward), JSON.stringify(eastward));
    assert.ok(eastward.west <= -179.99 && eastward.east >= -174.8, 'the larger east part is shown whole');
    assert.ok(eastward.south <= -19.2 && eastward.north >= -16.1);
});

test('only a single click dismisses the search marker, so a double-click zooms in around it', () => {
    const map = Object.assign(Object.create(OpenLayersMap.prototype), {
        map: new Observable(),
        searchMarkerLayer: new VectorLayer({ source: new VectorSource() }),
    });
    const markers = () => map.searchMarkerLayer.getSource().getFeatures();
    map.dismissSearchMarkerOnSingleClick();
    map.setSearchMarker([35.9, 56.86], 'Тверь');

    for (const type of ['click', 'click', 'dblclick']) map.map.dispatchEvent(type);
    assert.equal(markers().length, 1);
    map.map.dispatchEvent('singleclick');
    assert.equal(markers().length, 0);
});

test('restoring a saved or earlier map view clears the search marker', async t => {
    const previous = { Alpine: globalThis.Alpine, window: globalThis.window };
    globalThis.Alpine = { nextTick: async () => {} };
    globalThis.window = new EventTarget();
    t.after(() => Object.assign(globalThis, previous));
    const layout = { fullscreen: false, minimized: false };
    const track = new VectorSource();
    const map = Object.assign(Object.create(OpenLayersMap.prototype), {
        sharedConfig: { layout: () => layout }, sharedRestoreGeneration: 0, navigationRestoreGeneration: 0, disposed: false,
        view: createMapView({ center: [0, 0], zoom: 3 }), sourceState: { name: 'osm' },
        filters: { all: true }, overlays: {}, queryParameters: {},
        trackLayer: { getSource: () => track, isUploaded: { value: false }, clear() { track.clear(); } },
        tracks: { load: () => new Promise(() => {}), replace() {} },
        buffer: { setTrack() {}, clear() {} },
        map: { updateSize() {}, renderSync() {} },
        searchMarkerLayer: new VectorLayer({ source: new VectorSource() }),
        source(name) { this.sourceState.name = name; }, updateOverlays() {}, updateFilterStyles() {},
    });
    const markers = () => map.searchMarkerLayer.getSource().getFeatures();

    map.setSearchMarker([37.6, 55.7], 'Москва');
    await map.restoreSharedState({ version: 1, center: [30.3, 59.9], zoom: 11 });
    assert.equal(markers().length, 0, 'a saved map opened in the same page');

    map.setSearchMarker([37.6, 55.7], 'Москва');
    await map.restoreNavigationState({ center: fromLonLat([30.3, 59.9]), zoom: 11, sourceName: 'osm', filters: {}, overlays: {}, track: null });
    assert.equal(markers().length, 0, 'back or forward to an earlier view');
});
