<?php

namespace App\Support\Erp;

use App\Models\FormaPagamento;
use Illuminate\Support\Facades\Schema;

/**
 * Atalhos de teclado das formas de pagamento no fechamento (PDV / Tela de Venda / OS).
 *
 * Em caso de disputa na mesma tela de fechamento, a tecla NÃO entra no select
 * (não se desativa silenciosamente a função original).
 */
final class FormaPagamentoAtalho
{
    public const SEM_ATALHO = '';

    /**
     * F-keys exclusivas de meios (além das do pool Concluir que sobrarem).
     *
     * Vazio de propósito: F3/F4 são fiscais do PDV no mesmo modal; não competir.
     *
     * @var list<string>
     */
    public const ATALHOS_F_BASE = [];

    /**
     * Teclas F do pool Concluir que nunca podem ir para meios (mesmo se “livres”).
     * F7: Concluir no PDV quando configurado; F9: Transportadora na Tela de Venda.
     *
     * @var list<string>
     */
    public const ATALHOS_F_RESERVADOS_CONTEXTO = ['F7', 'F9'];

    /**
     * @return list<string>
     */
    public static function letrasPermitidas(): array
    {
        return range('A', 'Z');
    }

    /**
     * F do pool Concluir ainda livre e sem disputa contextual (hoje: só F10 se Concluir ≠ F10).
     *
     * @return list<string>
     */
    public static function teclasFPermitidas(?int $empresaId = null): array
    {
        $concluir = EmpresaParametros::atalhoConcluirVenda($empresaId);
        $permitidas = [];

        foreach (self::ATALHOS_F_BASE as $fx) {
            if ($fx !== $concluir) {
                $permitidas[] = $fx;
            }
        }

        foreach (EmpresaParametros::ATALHOS_CONCLUIR_VENDA_PERMITIDOS as $fx) {
            if ($fx === $concluir) {
                continue;
            }
            if (in_array($fx, self::ATALHOS_F_RESERVADOS_CONTEXTO, true)) {
                continue;
            }
            $permitidas[] = $fx;
        }

        return array_values(array_unique($permitidas));
    }

    /**
     * @return list<string>
     */
    public static function teclasPermitidas(?int $empresaId = null): array
    {
        return array_merge(self::letrasPermitidas(), self::teclasFPermitidas($empresaId));
    }

    public static function normalizar(mixed $valor): string
    {
        $key = strtoupper(trim((string) $valor));

        if ($key === '' || $key === '-' || $key === 'NENHUM' || $key === 'SEM' || $key === 'NONE') {
            return self::SEM_ATALHO;
        }

        if (preg_match('/^[A-Z]$/', $key) === 1) {
            return $key;
        }

        if (preg_match('/^F([1-9]|1[0-2])$/', $key) === 1) {
            return $key;
        }

        return $key;
    }

    public static function isPermitido(string $atalho, ?int $empresaId = null): bool
    {
        $atalho = self::normalizar($atalho);

        if ($atalho === self::SEM_ATALHO) {
            return true;
        }

        return in_array($atalho, self::teclasPermitidas($empresaId), true);
    }

    public static function isTeclaF(string $atalho): bool
    {
        return preg_match('/^F([1-9]|1[0-2])$/', self::normalizar($atalho)) === 1;
    }

    public static function isLetra(string $atalho): bool
    {
        return preg_match('/^[A-Z]$/', self::normalizar($atalho)) === 1;
    }

    /**
     * Mapa tecla → nome de quem ocupa (outros meios ou Concluir), para o select.
     *
     * @return array<string, string> atalho => nome exibido
     */
    public static function mapaOcupacao(?int $ignoreId = null, ?int $empresaId = null): array
    {
        $mapa = [];

        if (self::tabelaFormasExiste()) {
            $query = FormaPagamento::query()
                ->where('ativo', true)
                ->where('aparece_venda', true)
                ->whereNotNull('atalho')
                ->where('atalho', '!=', '')
                ->orderBy('codigo')
                ->orderBy('id');

            if ($ignoreId !== null && $ignoreId > 0) {
                $query->whereKeyNot($ignoreId);
            }

            foreach ($query->get(['atalho', 'descricao']) as $forma) {
                $key = self::normalizar($forma->atalho);

                if ($key === self::SEM_ATALHO || isset($mapa[$key])) {
                    continue;
                }

                $nome = mb_strtoupper(trim((string) $forma->descricao), 'UTF-8');
                $mapa[$key] = $nome !== '' ? $nome : 'outra forma';
            }
        }

        $concluir = EmpresaParametros::atalhoConcluirVenda($empresaId);

        if ($concluir !== '' && ! isset($mapa[$concluir])) {
            $mapa[$concluir] = 'Concluir Venda';
        }

        return $mapa;
    }

    private static function tabelaFormasExiste(): bool
    {
        try {
            return Schema::hasTable((new FormaPagamento)->getTable());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Opções do select com disabled + rótulo "em uso". Consulta o banco a cada chamada.
     *
     * @return list<array{value: string, label: string, disabled: bool}>
     */
    public static function opcoesSelectDetalhadas(
        ?string $valorAtual = null,
        ?int $ignoreId = null,
        ?int $empresaId = null,
    ): array {
        $atual = self::normalizar($valorAtual);
        $ocupacao = self::mapaOcupacao($ignoreId, $empresaId);
        $options = [];
        $vistos = [];

        $options[] = [
            'value' => self::SEM_ATALHO,
            'label' => 'Sem atalho',
            'disabled' => false,
        ];
        $vistos[self::SEM_ATALHO] = true;

        $candidatos = array_merge(
            self::letrasPermitidas(),
            self::teclasFPermitidas($empresaId),
        );

        $concluir = EmpresaParametros::atalhoConcluirVenda($empresaId);
        if ($concluir !== '' && ! in_array($concluir, $candidatos, true)) {
            $candidatos[] = $concluir;
        }

        foreach ($candidatos as $tecla) {
            $options[] = self::montarOpcaoSelect($tecla, $atual, $ocupacao);
            $vistos[$tecla] = true;
        }

        if ($atual !== self::SEM_ATALHO && ! isset($vistos[$atual])) {
            $ocupante = $ocupacao[$atual] ?? null;
            $label = $ocupante !== null
                ? $atual.' — '.$ocupante.' (em uso)'
                : $atual.' (inválido — altere)';

            // Próprio valor legado: permanece selecionável para o operador alterar.
            $options[] = [
                'value' => $atual,
                'label' => $label,
                'disabled' => false,
            ];
        }

        return $options;
    }

    /**
     * @param  array<string, string>  $ocupacao
     * @return array{value: string, label: string, disabled: bool}
     */
    private static function montarOpcaoSelect(string $tecla, string $atual, array $ocupacao): array
    {
        $tecla = self::normalizar($tecla);
        $eProprio = $tecla === $atual && $atual !== self::SEM_ATALHO;
        $ocupante = $ocupacao[$tecla] ?? null;

        if ($ocupante !== null && ! $eProprio) {
            return [
                'value' => $tecla,
                'label' => $tecla.' — '.$ocupante.' (em uso)',
                'disabled' => true,
            ];
        }

        return [
            'value' => $tecla,
            'label' => $tecla,
            'disabled' => false,
        ];
    }

    /**
     * @return array<string, string> value => label (sem flag disabled; preferir opcoesSelectDetalhadas na UI)
     */
    public static function opcoesSelect(
        ?string $valorAtual = null,
        ?int $ignoreId = null,
        ?int $empresaId = null,
    ): array {
        $options = [];

        foreach (self::opcoesSelectDetalhadas($valorAtual, $ignoreId, $empresaId) as $opt) {
            $options[$opt['value']] = $opt['label'];
        }

        return $options;
    }

    /**
     * Opções do select Concluir Venda (F7/F9/F10), com meios ocupando a tecla desabilitados.
     *
     * @return list<array{value: string, label: string, disabled: bool}>
     */
    public static function opcoesSelectConcluirDetalhadas(
        ?string $valorAtual = null,
        ?int $empresaId = null,
    ): array {
        $atual = EmpresaParametros::normalizarAtalhoConcluirVenda($valorAtual);
        $field = EmpresaParametros::atalhoConcluirVendaField();
        $options = [];

        foreach ($field['options'] as $value => $labelBase) {
            $value = self::normalizar((string) $value);
            $conflitos = self::formasComAtalho($value);
            $eAtual = $value === $atual;

            if ($conflitos !== [] && ! $eAtual) {
                $options[] = [
                    'value' => $value,
                    'label' => $labelBase.' — '.$conflitos[0]['descricao'].' (em uso)',
                    'disabled' => true,
                ];

                continue;
            }

            if ($conflitos !== [] && $eAtual) {
                // Config atual inconsistente: permanece selecionável + aviso; não grava se validar.
                $options[] = [
                    'value' => $value,
                    'label' => $labelBase.' — '.$conflitos[0]['descricao'].' (em uso)',
                    'disabled' => false,
                ];

                continue;
            }

            $options[] = [
                'value' => $value,
                'label' => $labelBase,
                'disabled' => false,
            ];
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    /**
     * Somente atalhos de meios (não inclui Concluir Venda).
     *
     * @return list<string>
     */
    public static function atalhosEmUsoNoFechamento(?int $ignoreId = null): array
    {
        if (! self::tabelaFormasExiste()) {
            return [];
        }

        $query = FormaPagamento::query()
            ->where('ativo', true)
            ->where('aparece_venda', true)
            ->whereNotNull('atalho')
            ->where('atalho', '!=', '');

        if ($ignoreId !== null && $ignoreId > 0) {
            $query->whereKeyNot($ignoreId);
        }

        $usados = [];

        foreach ($query->pluck('atalho') as $raw) {
            $key = self::normalizar($raw);

            if ($key !== self::SEM_ATALHO) {
                $usados[$key] = true;
            }
        }

        return array_keys($usados);
    }

    public static function meioUsaAtalho(string $atalho, ?int $ignoreId = null): bool
    {
        return self::formasComAtalho($atalho, $ignoreId) !== [];
    }

    /**
     * @return list<array{id: int, descricao: string}>
     */
    public static function formasComAtalho(string $atalho, ?int $ignoreId = null): array
    {
        $atalho = self::normalizar($atalho);

        if ($atalho === self::SEM_ATALHO || ! self::tabelaFormasExiste()) {
            return [];
        }

        $query = FormaPagamento::query()
            ->where('ativo', true)
            ->where('aparece_venda', true)
            ->whereRaw('UPPER(TRIM(atalho)) = ?', [$atalho])
            ->orderBy('codigo')
            ->orderBy('id');

        if ($ignoreId !== null && $ignoreId > 0) {
            $query->whereKeyNot($ignoreId);
        }

        return $query
            ->get(['id', 'descricao'])
            ->map(fn (FormaPagamento $f): array => [
                'id' => (int) $f->id,
                'descricao' => mb_strtoupper(trim((string) $f->descricao), 'UTF-8'),
            ])
            ->all();
    }

    public static function mensagemConflitoForma(string $atalho, string $outraDescricao): string
    {
        $atalho = self::normalizar($atalho);
        $outra = mb_strtoupper(trim($outraDescricao), 'UTF-8') ?: 'outra forma';

        return $atalho.' já está utilizado por '.$outra.'.';
    }

    public static function mensagemConflitoConcluir(string $atalho): string
    {
        $atalho = self::normalizar($atalho);

        return $atalho.' está reservado para Concluir Venda. Escolha outro atalho.';
    }

    public static function mensagemConflitoConcluirComForma(string $atalho, string $formaDescricao): string
    {
        $atalho = self::normalizar($atalho);
        $forma = mb_strtoupper(trim($formaDescricao), 'UTF-8') ?: 'uma forma de pagamento';

        return $atalho.' já está utilizado pelo meio de pagamento '.$forma.'.';
    }

    public static function validarParaForma(
        mixed $atalho,
        bool $ativo,
        bool $apareceVenda,
        ?int $ignoreId = null,
        ?int $empresaId = null,
    ): ?string {
        $key = self::normalizar($atalho);

        if ($key === self::SEM_ATALHO) {
            return null;
        }

        if (! self::isPermitido($key, $empresaId)) {
            return 'Atalho inválido. Use uma letra (A–Z) ou tecla F disponível na lista.';
        }

        if (! $ativo || ! $apareceVenda) {
            return null;
        }

        $concluir = EmpresaParametros::atalhoConcluirVenda($empresaId);

        if ($key === $concluir) {
            return self::mensagemConflitoConcluir($key);
        }

        $conflitos = self::formasComAtalho($key, $ignoreId);

        if ($conflitos !== []) {
            return self::mensagemConflitoForma($key, $conflitos[0]['descricao']);
        }

        return null;
    }

    public static function validarParaConcluir(mixed $atalhoConcluir, ?int $empresaId = null): ?string
    {
        $key = EmpresaParametros::normalizarAtalhoConcluirVenda($atalhoConcluir);
        $conflitos = self::formasComAtalho($key);

        if ($conflitos !== []) {
            return self::mensagemConflitoConcluirComForma($key, $conflitos[0]['descricao']);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function avisosInconsistencia(
        mixed $atalho,
        bool $ativo,
        bool $apareceVenda,
        ?int $ignoreId = null,
        ?int $empresaId = null,
    ): array {
        $key = self::normalizar($atalho);
        $avisos = [];

        if ($key === self::SEM_ATALHO) {
            return $avisos;
        }

        if (! self::isPermitido($key, $empresaId)) {
            $avisos[] = 'Atalho "'.$key.'" é inválido ou não está mais disponível. Altere antes de gravar.';
        }

        if ($ativo && $apareceVenda) {
            $concluir = EmpresaParametros::atalhoConcluirVenda($empresaId);

            if ($key === $concluir) {
                $avisos[] = self::mensagemConflitoConcluir($key);
            }

            foreach (self::formasComAtalho($key, $ignoreId) as $outra) {
                $avisos[] = self::mensagemConflitoForma($key, $outra['descricao']);
            }
        }

        return array_values(array_unique($avisos));
    }

    public static function cadastradoUtilizavel(string $atalho, array $usados): ?string
    {
        $atalho = self::normalizar($atalho);

        if ($atalho === self::SEM_ATALHO) {
            return null;
        }

        if (! self::isLetra($atalho) && ! self::isTeclaF($atalho)) {
            return null;
        }

        if (isset($usados[$atalho]) || in_array($atalho, $usados, true)) {
            return null;
        }

        return $atalho;
    }
}
