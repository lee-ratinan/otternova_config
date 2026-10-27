<?php
if (!function_exists('formatCurrency')) {
    /**
     * @param float $amount
     * @param string $currencyCode
     * @return string
     */
    function formatCurrency(float $amount, string $currencyCode = 'USD'): string {
        $currencyCode = strtoupper($currencyCode);
        // Map currency codes to their standard regional locales
        $currencyLocaleMap = [
            // Southeast Asia
            'THB' => 'th_TH',
            'SGD' => 'en_SG',
            'MYR' => 'ms_MY',
            'IDR' => 'id_ID',
            'PHP' => 'en_PH',
            'VND' => 'vi_VN',
            // East Asia
            'JPY' => 'ja_JP',
            'TWD' => 'zh_TW',
            'KRW' => 'ko_KR',
            // Oceania
            'AUD' => 'en_AU',
            'NZD' => 'en_NZ',
            // Majors & Europe
            'USD' => 'en_US',
            'GBP' => 'en_GB',
            'EUR' => 'de_DE', // Standard European format (e.g., 1.234,56 €)
        ];
        // Fallback to en_US if the currency code isn't found in the map
        $locale = $currencyLocaleMap[$currencyCode] ?? 'en_US';
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($amount, $currencyCode);
    }
}