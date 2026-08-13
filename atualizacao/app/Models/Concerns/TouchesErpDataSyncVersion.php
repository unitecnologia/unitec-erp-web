<?php

namespace App\Models\Concerns;

use App\Support\Erp\ErpDataSyncVersion;

/**
 * Ao salvar/excluir o model, incrementa a versão do canal para as listagens em rede.
 */
trait TouchesErpDataSyncVersion
{
    public static function bootTouchesErpDataSyncVersion(): void
    {
        static::saved(static function (): void {
            $channel = static::erpDataSyncChannel();

            if ($channel !== null) {
                ErpDataSyncVersion::bump($channel);
            }
        });

        static::deleted(static function (): void {
            $channel = static::erpDataSyncChannel();

            if ($channel !== null) {
                ErpDataSyncVersion::bump($channel);
            }
        });
    }

    /**
     * Canal de sync (ex.: ErpDataSyncVersion::CHANNEL_PRODUCTS). Null = não sincroniza.
     */
    protected static function erpDataSyncChannel(): ?string
    {
        return null;
    }
}
