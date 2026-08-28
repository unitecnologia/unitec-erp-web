@php
    use App\Support\Erp\Terminais\TerminalFormOptions;

    $marcas = TerminalFormOptions::withCurrentOption(
        TerminalFormOptions::marcasBalancaSerial(),
        $this->data['balanca_marca'] ?? null
    );
    $portas = TerminalFormOptions::withCurrentOption(
        TerminalFormOptions::portasBalanca(),
        $this->data['balanca_porta'] ?? null
    );
    $velocidades = TerminalFormOptions::withCurrentOption(
        TerminalFormOptions::velocidadesBalanca(),
        $this->data['balanca_velocidade'] ?? null
    );
    $dataBits = TerminalFormOptions::withCurrentOption(
        TerminalFormOptions::dataBitsBalanca(),
        $this->data['balanca_databits'] ?? null
    );
    $paridades = TerminalFormOptions::withCurrentOption(
        TerminalFormOptions::paridadesBalanca(),
        $this->data['balanca_paridade'] ?? null
    );
    $stopBits = TerminalFormOptions::withCurrentOption(
        TerminalFormOptions::stopBitsBalanca(),
        $this->data['balanca_stopbits'] ?? null
    );
    $handshakings = TerminalFormOptions::withCurrentOption(
        TerminalFormOptions::handshakingsBalanca(),
        $this->data['balanca_handshaking'] ?? null
    );
@endphp

<div class="erp-pcad-form erp-terminais-form erp-terminais-form--balanca">
    <div class="erp-terminais-balanca__stack">
        <div class="erp-pcad-form__row erp-terminais-balanca__field">
            <label class="erp-pcad-form__label" for="term-bal-marca">Balança</label>
            <select id="term-bal-marca" wire:model="data.balanca_marca" class="erp-pcad-form__select">
                @foreach ($marcas as $marca)
                    <option value="{{ $marca }}">{{ $marca === '' ? '— Nenhuma —' : $marca }}</option>
                @endforeach
            </select>
        </div>

        <div class="erp-pcad-form__row erp-terminais-balanca__field">
            <label class="erp-pcad-form__label" for="term-bal-porta">Porta</label>
            <select id="term-bal-porta" wire:model="data.balanca_porta" class="erp-pcad-form__select">
                <option value="">—</option>
                @foreach ($portas as $porta)
                    <option value="{{ $porta }}">{{ $porta }}</option>
                @endforeach
            </select>
        </div>

        <div class="erp-pcad-form__row erp-terminais-balanca__field">
            <label class="erp-pcad-form__label" for="term-bal-vel">Velocidade</label>
            <select id="term-bal-vel" wire:model="data.balanca_velocidade" class="erp-pcad-form__select">
                <option value="">—</option>
                @foreach ($velocidades as $vel)
                    <option value="{{ $vel }}">{{ $vel }}</option>
                @endforeach
            </select>
        </div>

        <div class="erp-pcad-form__row erp-terminais-balanca__field">
            <label class="erp-pcad-form__label" for="term-bal-data">Data Bits</label>
            <select id="term-bal-data" wire:model="data.balanca_databits" class="erp-pcad-form__select">
                <option value="">—</option>
                @foreach ($dataBits as $bits)
                    <option value="{{ $bits }}">{{ $bits }}</option>
                @endforeach
            </select>
        </div>

        <div class="erp-pcad-form__row erp-terminais-balanca__field">
            <label class="erp-pcad-form__label" for="term-bal-par">Paridade</label>
            <select id="term-bal-par" wire:model="data.balanca_paridade" class="erp-pcad-form__select">
                <option value="">—</option>
                @foreach ($paridades as $par)
                    <option value="{{ $par }}">{{ $par }}</option>
                @endforeach
            </select>
        </div>

        <div class="erp-pcad-form__row erp-terminais-balanca__field">
            <label class="erp-pcad-form__label" for="term-bal-stop">Stop Bits</label>
            <select id="term-bal-stop" wire:model="data.balanca_stopbits" class="erp-pcad-form__select">
                <option value="">—</option>
                @foreach ($stopBits as $stop)
                    <option value="{{ $stop }}">{{ $stop }}</option>
                @endforeach
            </select>
        </div>

        <div class="erp-pcad-form__row erp-terminais-balanca__field">
            <label class="erp-pcad-form__label" for="term-bal-hand">Handshaking</label>
            <select id="term-bal-hand" wire:model="data.balanca_handshaking" class="erp-pcad-form__select">
                <option value="">—</option>
                @foreach ($handshakings as $hs)
                    <option value="{{ $hs }}">{{ $hs }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <fieldset class="erp-terminais-form__checks erp-terminais-balanca__checks">
        <label class="erp-pcad__check"><input type="checkbox" wire:model="data.ler_peso"> Habilita leitura Peso no PDV</label>
    </fieldset>

    <div class="erp-terminais-balanca__test" wire:ignore>
        <button
            type="button"
            class="erp-terminais-balanca__test-btn"
            data-erp-test-scale
            data-erp-label="Testar balança"
        >
            Testar balança
        </button>
        <p
            class="erp-terminais-balanca__test-status"
            data-erp-test-scale-status
            role="status"
            aria-live="polite"
            hidden
        ></p>
        <p class="erp-terminais-balanca__test-help">
            Em teste com com0com: simulador em uma COM e ERP na outra; habilite
            <strong>Monitorar Requisição</strong>. Para Uran12, use 9600 / 8 / None / 2.
        </p>
    </div>

</div>
