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
    function formatCurrency(float $amount, string $currencyCode = 'USD'): string
    {
        $currencyCode = strtoupper($currencyCode);
        $supportedFormats = [
            "IDR" => "Rp###",
            "MYR" => "RM###",
            "PHP" => "₱###",
            "SGD" => "S$###",
            "VND" => "###₫",
            "THB" => "###บ.",
            "TWD" => "###元",
            "JPY" => "###円",
            "KRW" => "###원",
            "USD" => "$###"
        ];
        if (!isset($supportedFormats[$currencyCode])) {
            return "[INVALID CURRENCY CODE]";
        }
        $thousandsSeparator = ",";
        $decimalSeparator   = ".";
        $decimalPlaces      = 2;
        if (in_array($currencyCode, ["IDR", "VND"])) {
            $thousandsSeparator = ".";
            $decimalSeparator   = ",";
            $decimalPlaces      = 0;
        }
        if (in_array($currencyCode, ["TWD", "JPY", "KRW"])) {
            $decimalPlaces = 0;
        }
        if (0 == $decimalPlaces) {
            $amount = floor($amount);
        }
        $number = number_format($amount, $decimalPlaces, $decimalSeparator, $thousandsSeparator);
        return str_replace('###', $number, $supportedFormats[$currencyCode]);
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
                return $phoneUtil->format($numberProto, PhoneNumberFormat::INTERNATIONAL);
            }
        } catch (NumberParseException $e) {
            // If parsing fails, log it or handle quietly
            log_message('debug', 'Phone parsing failed: ' . $e->getMessage());
        }
        // Fallback to original input if invalid or parsing fails
        return $e164Number;
    }
}

if (!function_exists('formatDate')) {
    /**
     * Format date according to the locale
     * @param $date
     * @param $locale
     * @return false|string
     */
    function formatDate($date, $locale): false|string
    {
        $locale = match ($locale) {
            'th' => 'th_TH',
            default => 'en_US',
        };
        $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL);
        return $formatter->format($date);
    }
}