<?php

class MorposCurrency
{
    /**
     * Map 3-letter ISO currency codes to ISO-4217 numeric codes.
     * Extend this map as needed.
     *
     * @return array
     */
    public static function numericMap()
    {
        return array(
            'TRY' => '949',
            'USD' => '840',
            'EUR' => '978',
        );
    }

    /**
     * Convert 3-letter code to numeric ISO-4217 code.
     * Returns null if not found.
     */
    public static function toNumeric($alpha3)
    {
        $map = self::numericMap();

        $alpha3 = strtoupper(trim($alpha3));

        return isset($map[$alpha3]) ? $map[$alpha3] : null;
    }
}
