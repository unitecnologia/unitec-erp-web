<?php

namespace Unitec\FiscalEngine\Xml;

/**
 * Literais canônicos exigidos pelo schema SEFAZ para CC-e (tpEvento 110110).
 */
final class NfeCartaCorrecaoLiterals
{
    public const DESC_EVENTO = 'Carta de Correcao';

    /** Texto sem acentuação — deve ser idêntico ao leiaute oficial. */
    public const X_COND_USO = 'A Carta de Correcao e disciplinada pelo paragrafo 1o-A do art. 7o do Convenio S/N, de 15 de dezembro de 1970 e pode ser utilizada para regularizacao de erro ocorrido na emissao de documento fiscal, desde que o erro nao esteja relacionado com: I - as variaveis que determinam o valor do imposto tais como: base de calculo, aliquota, diferenca de preco, quantidade, valor da operacao ou da prestacao; II - a correcao de dados cadastrais que implique mudanca do remetente ou do destinatario; III - a data de emissao ou de saida.';
}
