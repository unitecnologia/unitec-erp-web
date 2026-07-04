<?php



namespace App\Support\Erp\Dashboard;



use App\Models\Empresa;

use App\Models\VendasParametro;

use App\Support\Erp\Nfe\NfeFiscalConfig;

use Carbon\Carbon;

use Illuminate\Support\Facades\Auth;



final class ErpDashboardCertificadoAlert

{

    public static function resolveEmpresaId(): ?int

    {

        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);



        if ($empresaId) {

            return (int) $empresaId;

        }



        $fallback = Empresa::query()->where('ativo', true)->orderBy('id')->value('id');



        return $fallback ? (int) $fallback : null;

    }



    /**

     * @return array{tone: string, title: string, time: string, featured?: bool}|null

     */

    public static function fromEmpresa(?int $empresaId = null): ?array

    {

        $empresaId ??= self::resolveEmpresaId();



        if (! $empresaId) {

            return null;

        }



        $params = VendasParametro::query()->find($empresaId);



        if ($params === null) {

            return null;

        }



        $path = NfeFiscalConfig::certificadoAbsolutePath($params);

        $senha = $params->safeSenhaCertificado();



        if ($path === null || ($senha === null && ! $params->hasStoredSenhaCertificado())) {

            return [

                'tone' => 'orange',

                'title' => 'Certificado A1 não configurado',

                'time' => 'Hoje',

                'featured' => true,

            ];

        }



        if ($senha === null) {

            return [

                'tone' => 'red',

                'title' => 'Senha do certificado precisa ser reconfigurada nas configurações fiscais',

                'time' => 'Hoje',

                'featured' => true,

            ];

        }



        $result = NfeFiscalConfig::readPkcs12(file_get_contents($path), $senha);



        if (! $result['ok']) {

            return [

                'tone' => 'red',

                'title' => 'Certificado A1 não pôde ser lido',

                'time' => 'Hoje',

                'featured' => true,

            ];

        }



        $validade = Carbon::createFromFormat('d/m/Y', (string) $result['validade'])->startOfDay();

        $dias = (int) now()->startOfDay()->diffInDays($validade, false);



        return [

            'tone' => self::alertTone($dias),

            'title' => self::alertTitle($dias),

            'time' => 'Hoje',

            'featured' => true,

        ];

    }



    public static function alertTitle(int $dias): string

    {

        if ($dias < 0) {

            $expirados = abs($dias);



            return $expirados === 1

                ? 'Certificado A1 vencido há 1 dia'

                : "Certificado A1 vencido há {$expirados} dias";

        }



        if ($dias === 0) {

            return 'Certificado A1 vence hoje';

        }



        if ($dias === 1) {

            return 'Certificado A1 vence em 1 dia';

        }



        return "Certificado A1 vence em {$dias} dias";

    }



    public static function alertTone(int $dias): string

    {

        if ($dias < 0 || $dias <= 7) {

            return 'red';

        }



        if ($dias <= 30) {

            return 'yellow';

        }



        return 'green';

    }

}


