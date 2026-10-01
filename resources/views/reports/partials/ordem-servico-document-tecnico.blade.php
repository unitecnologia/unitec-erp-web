<div class="os-doc__section">
    <div class="os-doc__section-title">Técnico responsável</div>
    <div class="os-doc__section-body">
        <table class="os-doc__kv">
            <tr>
                <td class="os-doc__kv-label">Nome</td>
                <td>{{ $tecnico !== '' ? mb_strtoupper($tecnico, 'UTF-8') : '—' }}</td>
            </tr>
            <tr>
                <td class="os-doc__kv-label">Início</td>
                <td>{{ $abertura !== '' ? $abertura : '—' }}</td>
            </tr>
            <tr>
                <td class="os-doc__kv-label">Término</td>
                <td>{{ $conclusao !== '' ? $conclusao : '—' }}</td>
            </tr>
        </table>
    </div>
</div>
