<?php



namespace App\Support\Erp\WhatsApp;



class WhatsAppPhone

{

    public static function digitsOnly(?string $value): string

    {

        return preg_replace('/\D/', '', $value ?? '') ?? '';

    }



    public static function normalize(?string $value): ?string

    {

        $digits = self::digitsOnly($value);



        if ($digits === '') {

            return null;

        }



        $local = self::stripCountryCode($digits);



        if (strlen($local) === 10 || strlen($local) === 11) {

            return '55' . self::withMobileNine($local);

        }



        if (strlen($digits) >= 12 && str_starts_with($digits, '55')) {

            $local = substr($digits, 2);



            if (strlen($local) <= 11) {

                return '55' . self::withMobileNine($local);

            }



            return $digits;

        }



        return strlen($digits) >= 12 ? $digits : null;

    }



    /**

     * @return list<string>

     */

    public static function lookupCandidates(?string $value): array

    {

        $normalized = self::normalize($value);



        if ($normalized === null) {

            return [];

        }



        $local = substr($normalized, 2);

        $candidates = [$normalized];



        $legacy = self::withoutMobileNine($local);



        if ($legacy !== null) {

            $legacyFull = '55' . $legacy;



            if (! in_array($legacyFull, $candidates, true)) {

                $candidates[] = $legacyFull;

            }

        }



        return $candidates;

    }



    public static function formatDisplay(?string $digits): string

    {

        $normalized = self::normalize($digits);



        if ($normalized === null) {

            return '';

        }



        if (str_starts_with($normalized, '55') && strlen($normalized) >= 12) {

            $local = substr($normalized, 2);

            $ddd = substr($local, 0, 2);

            $rest = substr($local, 2);



            if (strlen($rest) === 9) {

                return sprintf('(%s) %s-%s', $ddd, substr($rest, 0, 5), substr($rest, 5));

            }



            if (strlen($rest) === 8) {

                return sprintf('(%s) %s-%s', $ddd, substr($rest, 0, 4), substr($rest, 4));

            }

        }



        return $normalized;

    }



    public static function isValidMobile(?string $value): bool

    {

        if (blank($value)) {

            return true;

        }



        $digits = self::digitsOnly($value);

        $local = self::stripCountryCode($digits);



        return strlen($local) === 11;

    }



    public static function mensagemCelular(?string $value): ?string

    {

        if (blank($value)) {

            return null;

        }



        if (self::isValidMobile($value)) {

            return null;

        }



        return 'Informe o celular com DDD e 11 dígitos.';

    }



    protected static function stripCountryCode(string $digits): string

    {

        if (str_starts_with($digits, '55') && strlen($digits) > 11) {

            return substr($digits, 2);

        }



        return $digits;

    }



    protected static function withMobileNine(string $local): string

    {

        if (strlen($local) === 10 && self::isBrazilianMobileSubscriber(substr($local, 2))) {

            return substr($local, 0, 2) . '9' . substr($local, 2);

        }



        if (strlen($local) === 11 && $local[2] === '9' && self::isBrazilianMobileSubscriber(substr($local, 3))) {

            return $local;

        }



        return $local;

    }



    protected static function withoutMobileNine(string $local): ?string

    {

        if (strlen($local) === 11 && $local[2] === '9' && self::isBrazilianMobileSubscriber(substr($local, 3))) {

            return substr($local, 0, 2) . substr($local, 3);

        }



        if (strlen($local) === 10 && self::isBrazilianMobileSubscriber(substr($local, 2))) {

            return $local;

        }



        return null;

    }



    protected static function isBrazilianMobileSubscriber(string $subscriber): bool

    {

        $first = $subscriber[0] ?? '';



        return in_array($first, ['6', '7', '8', '9'], true);

    }

}

