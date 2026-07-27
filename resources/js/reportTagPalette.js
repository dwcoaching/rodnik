const STORAGE_KEY = 'rodnik.reportTagPalette.v1';

const DEFAULT_PALETTE = Object.freeze({
    success: Object.freeze({
        border: '#bbf7d0',
        background: '#f0fdf4',
        text: '#14532d',
    }),
    warning: Object.freeze({
        border: '#facc15',
        background: '#facc15',
        text: '#000000',
    }),
    danger: Object.freeze({
        border: '#fecaca',
        background: '#fef2f2',
        text: '#7f1d1d',
    }),
});

const paletteTypes = Object.keys(DEFAULT_PALETTE);
const colorRoles = ['border', 'background', 'text'];

function clonePalette(palette) {
    return Object.fromEntries(
        paletteTypes.map((type) => [
            type,
            Object.fromEntries(colorRoles.map((role) => [role, palette[type][role]])),
        ]),
    );
}

function isHexColor(value) {
    return typeof value === 'string' && /^#[0-9a-f]{6}$/i.test(value);
}

function normalizePalette(value) {
    const palette = clonePalette(DEFAULT_PALETTE);

    if (! value || typeof value !== 'object') {
        return palette;
    }

    paletteTypes.forEach((type) => {
        colorRoles.forEach((role) => {
            if (isHexColor(value[type]?.[role])) {
                palette[type][role] = value[type][role].toLowerCase();
            }
        });
    });

    return palette;
}

function loadPalette() {
    try {
        return normalizePalette(JSON.parse(localStorage.getItem(STORAGE_KEY)));
    } catch (error) {
        return clonePalette(DEFAULT_PALETTE);
    }
}

function applyPalette(value) {
    const palette = normalizePalette(value);

    paletteTypes.forEach((type) => {
        colorRoles.forEach((role) => {
            document.documentElement.style.setProperty(
                `--report-tag-${type}-${role}`,
                palette[type][role],
            );
        });
    });

    return palette;
}

function savePalette(value) {
    const palette = applyPalette(value);

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(palette));
    } catch (error) {
        // The preview still works when storage is unavailable.
    }

    return palette;
}

window.reportTagPalette = {
    defaults: () => clonePalette(DEFAULT_PALETTE),
    load: loadPalette,
    apply: applyPalette,
    save: savePalette,
    serialize: (value) => JSON.stringify(normalizePalette(value)),
};

window.reportTagPalettePicker = function() {
    return {
        isOpen: false,
        copied: false,
        palette: loadPalette(),
        savedPalette: loadPalette(),

        init() {
            this.palette = applyPalette(this.savedPalette);
        },

        open() {
            this.savedPalette = loadPalette();
            this.palette = clonePalette(this.savedPalette);
            this.copied = false;
            this.isOpen = true;
            this.$nextTick(() => this.$refs.closeButton.focus());
        },

        preview() {
            this.palette = applyPalette(this.palette);
            this.copied = false;
        },

        save() {
            this.palette = savePalette(this.palette);
            this.savedPalette = clonePalette(this.palette);
            this.isOpen = false;
        },

        cancel() {
            this.palette = applyPalette(this.savedPalette);
            this.isOpen = false;
        },

        reset() {
            this.palette = window.reportTagPalette.defaults();
            this.preview();
        },

        async copy() {
            try {
                await navigator.clipboard.writeText(window.reportTagPalette.serialize(this.palette));
                this.copied = true;
            } catch (error) {
                this.copied = false;
            }
        },
    };
};

applyPalette(loadPalette());
