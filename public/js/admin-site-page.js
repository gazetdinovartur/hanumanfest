(function () {
    function syncKitchenVideos() {
        const select = document.querySelector('[data-hf-page-template]');
        const box = document.querySelector('.hf-kitchen-videos');
        if (!box) {
            return;
        }
        const value = select ? String(select.value) : '';
        const kitchen = value === 'kitchen';
        box.classList.toggle('is-visible', kitchen);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncKitchenVideos);
    } else {
        syncKitchenVideos();
    }

    document.addEventListener('change', function (event) {
        if (event.target && event.target.matches('[data-hf-page-template]')) {
            syncKitchenVideos();
        }
    });
})();
