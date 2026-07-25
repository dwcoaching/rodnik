export default function trans(key, fallback = key) {
    return window.rodnikTranslations?.[key] ?? fallback;
}
