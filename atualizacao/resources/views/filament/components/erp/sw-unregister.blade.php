{{-- Remove Service Workers legados — roda antes de qualquer outro script. --}}

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

