export default coordinates => ({
    coordinates,
    visible: false,

    init() {
        this.refresh();
    },

    refresh() {
        const map = window.rodnikMap;
        // The camera settles on moveend; judging it mid-flight would flash the control during a focus animation.
        if (!map || window.rodnikNavigation?.restoring || map.view.getAnimating()) return;
        this.visible = !map.isSpringFocused(this.coordinates);
    },

    showOnMap() {
        window.rodnikMap?.focusSpring(this.coordinates);
        this.visible = false;
    },
});
