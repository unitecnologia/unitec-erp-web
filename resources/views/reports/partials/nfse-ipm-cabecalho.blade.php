<table class="ipm" cellspacing="0" cellpadding="0">
    <tr>
        <td rowspan="2" class="ipm__prestador" style="width: 49%;">
            <div class="ipm__forte">{{ $p['nome'] }}</div>
            <div><b>CNPJ:</b> {{ $p['documento'] }}</div>
            <div>{{ $p['endereco'] }}</div>
            <div>{{ $p['cep_bairro'] }}</div>
            <div>Município: {{ $p['municipio'] }}</div>
            <div><b>Insc. Municipal:</b> {{ $p['im'] !== '' ? $p['im'] : 'Não informado' }} - <b>Insc. Estadual:</b> {{ $p['ie'] }}</div>
            <div>Telefone: {{ $p['telefone'] }} - Celular: {{ $p['celular'] }}</div>
        </td>
        <td class="ipm__centro ipm__meio" style="width: 17%;">
            Número da NFS-e<br>
            <b>{{ $d['numero'] }}</b>
        </td>
        <td class="ipm__centro ipm__meio" style="width: 17%;">
            Situação<br>
            <b>{{ $d['situacao'] }}</b>
        </td>
        <td rowspan="2" class="ipm__qr" style="width: 17%;">
            @if (! empty($d['qr_data_uri']))
                <img src="{{ $d['qr_data_uri'] }}" alt="QR Code">
            @endif
            @if (! empty($d['consulta_url']))
                <a href="{{ $d['consulta_url'] }}">Autenticidade</a>
            @else
                <span>Autenticidade</span>
            @endif
        </td>
    </tr>
    <tr>
        <td></td>
        <td class="ipm__centro ipm__meio">
            Tipo<br>
            <b>{{ $d['tipo'] }}</b>
        </td>
    </tr>
</table>

<table class="ipm" cellspacing="0" cellpadding="0">
    <tr>
        <td colspan="2" class="ipm__titulo">{{ $titulo }}</td>
    </tr>
    <tr>
        <td rowspan="2" class="ipm__pref" style="width: 55%;">
            <table class="ipm__pref-tabela" cellspacing="0" cellpadding="0">
                <tr>
                    @if (! empty($p['brasao']))
                        <td class="ipm__brasao"><img src="{{ $p['brasao'] }}" alt="Brasão"></td>
                    @endif
                    <td>
                        @if ($p['estado'] !== '')
                            <div class="ipm__forte">ESTADO DE {{ $p['estado'] }}</div>
                        @endif
                        @if ($p['prefeitura'] !== '')
                            <div class="ipm__forte">PREFEITURA MUNICIPAL DE {{ $p['prefeitura'] }}</div>
                        @endif
                        @if (($p['secretaria'] ?? '') !== '')
                            <div>{{ $p['secretaria'] }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
        <td class="ipm__centro ipm__ident">
            <div><b>Identificador</b></div>
            <div>{{ $d['identificador'] }}</div>
            @if (! empty($d['codigo_barras']))
                <div class="ipm__barras"><img src="{{ $d['codigo_barras'] }}" alt="Código de barras"></div>
            @endif
            @if ($mostrarChaveNacional ?? true)
                <div><b>Chave de Acesso NFS-e Nacional</b></div>
                <div>{{ $d['chave_acesso'] }}</div>
            @endif
        </td>
    </tr>
    <tr>
        <td class="ipm__sem-pad">
            <table class="ipm ipm--interna" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="ipm__centro" style="width: 50%;">Data Fato Gerador<br><b>{{ $d['fato_gerador'] }}</b></td>
                    <td class="ipm__centro" style="width: 50%;">Data/Hora Emissão<br><b>{{ $d['emissao'] }}</b></td>
                </tr>
            </table>
        </td>
    </tr>
</table>
