@isset($snapshot)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.UnitecGestorPwa) {
                window.UnitecGestorPwa.saveSnapshot(@json($snapshot));
                window.UnitecGestorPwa.saveTema(@json($this->gestorTema));
            }
        });
        document.addEventListener('livewire:navigated', function () {
            if (window.UnitecGestorPwa) {
                window.UnitecGestorPwa.saveSnapshot(@json($snapshot));
                window.UnitecGestorPwa.saveTema(@json($this->gestorTema));
            }
        });
    </script>
@endisset
