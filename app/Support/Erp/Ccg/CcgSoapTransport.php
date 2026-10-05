<?php

namespace App\Support\Erp\Ccg;

use Unitec\FiscalEngine\Certificate\Certificate;

interface CcgSoapTransport
{
    public function post(string $url, string $envelope, Certificate $certificate, int $timeoutSeconds): string;
}
