

<script>

    (function () {

        if ('serviceWorker' in navigator) {

            navigator.serviceWorker.getRegistrations().then(function (registrations) {

                registrations.forEach(function (registration) {

                    registration.unregister();

                });

            });

        }



        if ('caches' in window) {

            caches.keys().then(function (keys) {

                keys.forEach(function (key) {

                    caches.delete(key);

                });

            });

        }

    })();

</script>

<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/sw-unregister.blade.php ENDPATH**/ ?>