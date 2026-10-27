<?php

use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;

if (!function_exists('formatCurrency')) {
    /**
     * Format amount in the target currency
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

if (!function_exists('formatPhoneNumber')) {
    /**
     * Format phone number for showing on the screen
     * @param string $e164Number
     * @return string
     */
    function formatPhoneNumber(string $e164Number): string
    {
        $phoneUtil = PhoneNumberUtil::getInstance();
        try {
            // libphonenumber automatically detects the country from the E.164 '+' prefix
            $numberProto = $phoneUtil->parse($e164Number, null);
            if ($phoneUtil->isValidNumber($numberProto)) {
                // NATIONAL format converts +813XXXX -> 03-XXXX-XXXX (handles all 400+ JP codes)
                $countryCode = strtolower($phoneUtil->getRegionCodeForNumber($numberProto));
                return '<span class="fi fi-' . $countryCode . '"></span> ' . $phoneUtil->format($numberProto, PhoneNumberFormat::NATIONAL);
            }
        } catch (NumberParseException $e) {
            // If parsing fails, log it or handle quietly
            log_message('debug', 'Phone parsing failed: ' . $e->getMessage());
        }
        // Fallback to original input if invalid or parsing fails
        return $e164Number;
    }
}