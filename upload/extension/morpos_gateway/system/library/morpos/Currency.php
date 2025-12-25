<?php

namespace Opencart\Extension\MorposGateway\System\Library\Morpos;

class Currency
{
    /**
     * Map 3-letter ISO currency codes to ISO-4217 numeric codes.
     * Extend this map as needed.
     *
     * @return array<string,string>
     */
    public static function numericMap(): array
    {
        return [
            'TRY' => '949',
            'USD' => '840',
            'EUR' => '978',
        ];
    }

    /**
     * Convert 3-letter code to numeric ISO-4217 code.
     * Returns null if not found.
     *
     * @param string $alpha3
     * @return string|null
     */
    public static function toNumeric(string $alpha3): ?string
    {
        $map = self::numericMap();

        $alpha3 = strtoupper(trim($alpha3));

        return $map[$alpha3] ?? null;
    }
}
