@php
    $numeroVale = preg_replace('/\s+/', '', (string) $this->numeroDisplay) ?: '0';
    $codigoVale = ctype_digit($numeroVale) ? str_pad($numeroVale, 6, '0', STR_PAD_LEFT) : $numeroVale;

    $foneVale = preg_replace('/\D/', '', (string) $this->valeEmpresaFone) ?? '';
    if (strlen($foneVale) === 11) {
        $foneVale = '('.substr($foneVale, 0, 2).') '.substr($foneVale, 2, 5).'-'.substr($foneVale, 7);
    } elseif (strlen($foneVale) === 10) {
        $foneVale = '('.substr($foneVale, 0, 2).') '.substr($foneVale, 2, 4).'-'.substr($foneVale, 6);
    } else {
        $foneVale = trim((string) $this->valeEmpresaFone);
    }

    $formatarCep = function (string $texto): string {
        $digitos = preg_replace('/\D/', '', $texto) ?? '';

        return strlen($digitos) === 8
            ? 'CEP '.substr($digitos, 0, 5).'-'.substr($digitos, 5)
            : 'CEP '.trim($texto);
    };

    $enderecoLinhas = array_values(array_filter(array_map(
        'trim',
        preg_split('/\s*·\s*|\r?\n+/', (string) $this->valeEmpresaEndereco) ?: []
    )));

    if (count($enderecoLinhas) === 1) {
        $unica = $enderecoLinhas[0];
        $cepLinha = '';
        if (preg_match('/^(.*?)(?:\s*-\s*)?CEP\s*([\d.\-]+)\s*$/iu', $unica, $cepMatch)) {
            $unica = trim($cepMatch[1], " \t-");
            $cepLinha = $formatarCep($cepMatch[2]);
        }
        $linhas = [];
        if (preg_match('/^(.+?),\s*(\d+\w?)\s*,\s*(.+)$/u', $unica, $ruaMatch)) {
            $linhas[] = trim($ruaMatch[1]).', '.trim($ruaMatch[2]);
            $linhas[] = trim($ruaMatch[3]);
        } elseif ($unica !== '') {
            $linhas[] = $unica;
        }
        if ($cepLinha !== '') {
            $linhas[] = $cepLinha;
        }
        $enderecoLinhas = $linhas;
    } else {
        $enderecoLinhas = array_map(function (string $linha) use ($formatarCep): string {
            return preg_match('/^CEP\s*([\d.\-]+)$/iu', $linha, $cepMatch)
                ? $formatarCep($cepMatch[1])
                : $linha;
        }, $enderecoLinhas);
    }

    $padroes = [
        '0' => 'nnnwwnwnn',
        '1' => 'wnnwnnnnw',
        '2' => 'nnwwnnnnw',
        '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw',
        '5' => 'wnnwwnnnn',
        '6' => 'nnwwwnnnn',
        '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn',
        '9' => 'nnwwnnwnn',
        '*' => 'nwnnwnwnn',
    ];
    $barras = [];
    $cursor = 10;
    $chars = str_split('*'.strtoupper($codigoVale).'*');
    foreach ($chars as $indice => $char) {
        $padrao = $padroes[$char] ?? $padroes['0'];
        for ($bit = 0; $bit < 9; $bit++) {
            $largura = $padrao[$bit] === 'w' ? 3 : 1;
            if ($bit % 2 === 0) {
                $barras[] = ['x' => $cursor, 'w' => $largura];
            }
            $cursor += $largura;
        }
        if ($indice < count($chars) - 1) {
            $cursor += 1;
        }
    }
    $barcodeLargura = $cursor + 10;
@endphp

<aside class="erp-devvenda-side" aria-label="Prévia do Vale Troca">
    <h3 class="erp-devvenda-side__title">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 8V4h10v4"/><path d="M7 16H5a2 2 0 0 1-2-2v-4h18v4a2 2 0 0 1-2 2h-2"/><path d="M7 13h10v7H7z"/></svg>
        Prévia do Vale Troca
    </h3>

    <div class="erp-devvenda-vale" id="erp-devvenda-vale">
        <header class="erp-devvenda-vale__loja">
            <span class="erp-devvenda-vale__icone" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6L5 3H2"/><circle cx="9" cy="20" r="1.2"/><circle cx="17" cy="20" r="1.2"/></svg>
            </span>
            <strong>{{ $this->valeEmpresaNome !== '' ? $this->valeEmpresaNome : 'Sua loja' }}</strong>
            @foreach ($enderecoLinhas as $linha)
                <span @class(['is-cep' => str_starts_with($linha, 'CEP ')])>{{ $linha }}</span>
            @endforeach
            @if ($this->valeEmpresaCnpj !== '')
                <span>CNPJ: {{ $this->valeEmpresaCnpj }}</span>
            @endif
            @if ($foneVale !== '')
                <span>Fone: {{ $foneVale }}</span>
            @endif
        </header>

        <h4>Vale Troca</h4>

        <div class="erp-devvenda-vale__meta">
            <div>
                <span>Nº Troca</span>
                <strong>{{ $codigoVale }}</strong>
            </div>
            <div class="erp-devvenda-vale__meta-linha">
                <span>Data: {{ $this->dataDevolucaoDisplay() ?: '—' }}</span>
                <span>Hora: {{ $this->horaDevolucao !== '' ? $this->horaDevolucao : '—' }}</span>
            </div>
            <div>
                <span>Operador:</span>
                <strong>{{ $this->valeOperador !== '' ? $this->valeOperador : '—' }}</strong>
            </div>
        </div>

        <table class="erp-devvenda-vale__itens">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Qtde</th>
                    <th>Valor (R$)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->itens as $item)
                    <tr wire:key="vale-{{ $item['key'] ?? $loop->index }}">
                        <td>{{ $item['produto_descricao'] ?: '—' }}</td>
                        <td>{{ $item['qtd'] }}</td>
                        <td>{{ $item['total'] }}</td>
                    </tr>
                @empty
                    <tr class="is-empty">
                        <td colspan="3">Selecione a venda para ver os itens.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <p class="erp-devvenda-vale__total">
            <span>Total da Troca:</span>
            <strong>R$ {{ $this->totalDisplay }}</strong>
        </p>

        <svg class="erp-devvenda-vale__barcode" viewBox="0 0 {{ $barcodeLargura }} 42" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
            @foreach ($barras as $barra)
                <rect x="{{ $barra['x'] }}" y="0" width="{{ $barra['w'] }}" height="42" fill="#111"/>
            @endforeach
        </svg>
        <p class="erp-devvenda-vale__codigo">{{ $codigoVale }}</p>
        <p class="erp-devvenda-vale__hint">Apresente este vale no PDV para utilizar.</p>
        <p class="erp-devvenda-vale__hint">Válido por 90 dias.</p>
        <p class="erp-devvenda-vale__hint">Obrigado pela sua preferência!</p>
    </div>
</aside>
