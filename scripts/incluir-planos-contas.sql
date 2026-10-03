-- Plano de contas padrão (1.01 a 2.15).
-- HeidiSQL: abra o banco unitec_erp e execute este arquivo (F9).
-- Conta que já existir com o mesmo código não é alterada.

SET NAMES utf8mb4;

INSERT IGNORE INTO `unitec_planos_contas`
    (`codigo`, `descricao`, `dc`, `conta_completa`, `despesas`, `compras`, `entradas`, `ativo`, `created_at`, `updated_at`)
VALUES
    (101, 'VENDAS DE MERCADORIAS', 'C', '1.01', 0, 0, 0, 1, NOW(), NOW()),
    (102, 'PRESTAÇÃO DE SERVIÇOS', 'C', '1.02', 0, 0, 0, 1, NOW(), NOW()),
    (103, 'RECEBIMENTO DE CLIENTES', 'C', '1.03', 0, 0, 0, 1, NOW(), NOW()),
    (104, 'JUROS RECEBIDOS', 'C', '1.04', 0, 0, 0, 1, NOW(), NOW()),
    (105, 'OUTRAS RECEITAS', 'C', '1.05', 0, 0, 0, 1, NOW(), NOW()),
    (201, 'COMPRA DE MERCADORIAS', 'D', '2.01', 0, 0, 0, 1, NOW(), NOW()),
    (202, 'FORNECEDORES', 'D', '2.02', 0, 0, 0, 1, NOW(), NOW()),
    (203, 'ALUGUEL', 'D', '2.03', 0, 0, 0, 1, NOW(), NOW()),
    (204, 'ENERGIA ELÉTRICA', 'D', '2.04', 0, 0, 0, 1, NOW(), NOW()),
    (205, 'ÁGUA', 'D', '2.05', 0, 0, 0, 1, NOW(), NOW()),
    (206, 'TELEFONE / INTERNET', 'D', '2.06', 0, 0, 0, 1, NOW(), NOW()),
    (207, 'SALÁRIOS / PRÓ-LABORE', 'D', '2.07', 0, 0, 0, 1, NOW(), NOW()),
    (208, 'IMPOSTOS / TAXAS', 'D', '2.08', 0, 0, 0, 1, NOW(), NOW()),
    (209, 'TARIFAS BANCÁRIAS', 'D', '2.09', 0, 0, 0, 1, NOW(), NOW()),
    (210, 'FRETES / TRANSPORTES', 'D', '2.10', 0, 0, 0, 1, NOW(), NOW()),
    (211, 'MANUTENÇÃO / REPAROS', 'D', '2.11', 0, 0, 0, 1, NOW(), NOW()),
    (212, 'MATERIAL DE USO / CONSUMO', 'D', '2.12', 0, 0, 0, 1, NOW(), NOW()),
    (213, 'MARKETING / PUBLICIDADE', 'D', '2.13', 0, 0, 0, 1, NOW(), NOW()),
    (214, 'DESPESAS ADMINISTRATIVAS', 'D', '2.14', 0, 0, 0, 1, NOW(), NOW()),
    (215, 'OUTRAS DESPESAS', 'D', '2.15', 0, 0, 0, 1, NOW(), NOW());
