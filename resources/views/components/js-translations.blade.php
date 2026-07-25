<script>
    window.rodnikLocale = @js(app()->getLocale());
    window.rodnikPublicBaseUrl = @js(rtrim(url(localized_public_path()), '/'));
    window.rodnikTranslations = @js([
        'upload_failed' => __('ui.javascript.upload_failed'),
        'upload_timed_out' => __('ui.javascript.upload_timed_out'),
        'network_error' => __('ui.javascript.network_error'),
        'upload_cancelled' => __('ui.javascript.upload_cancelled'),
        'please_upload_gpx' => __('ui.javascript.please_upload_gpx'),
        'photo_no_coordinates' => __('ui.javascript.photo_no_coordinates'),
        'zoom_in_to_export' => __('ui.javascript.zoom_in_to_export'),
        'zoom_in' => __('ui.javascript.zoom_in'),
        'zoom_out' => __('ui.javascript.zoom_out'),
        'reset_rotation' => __('ui.javascript.reset_rotation'),
        'attributions' => __('ui.javascript.attributions'),
        'photo_close' => __('ui.javascript.photo_close'),
        'photo_zoom' => __('ui.javascript.photo_zoom'),
        'photo_previous' => __('ui.javascript.photo_previous'),
        'photo_next' => __('ui.javascript.photo_next'),
        'photo_load_error' => __('ui.javascript.photo_load_error'),
    ]);
</script>
