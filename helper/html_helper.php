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

if (!function_exists('formatPhoneNumberStandard')) {
    /**
     * Format phone number to the E.164 standard, i.e. 0812345678 with TH -> +66812345678
     * @param string $phoneNumber
     * @param string $countryCode ISO 3166-1 alpha-2 code, i.e. TH
     * @return string|false
     */
    function formatPhoneNumberStandard(string $phoneNumber, string $countryCode): string|false
    {
        $phoneUtil   = PhoneNumberUtil::getInstance();
        $countries   = listSupportedCountries();
        $countryCode = strtoupper($countryCode);
        if (!isset($countries[$countryCode])) {
            // Not supported country code
            log_message('debug', 'Not supported country code: ' . $countryCode);
            return false;
        }
        try {
            // $countryCode is only used when $phoneNumber does not already start with '+'
            $numberProto = $phoneUtil->parse($phoneNumber, strtoupper($countryCode));
            if ($phoneUtil->isValidNumber($numberProto)) {
                return $phoneUtil->format($numberProto, PhoneNumberFormat::E164);
            }
        } catch (NumberParseException $e) {
            log_message('debug', 'Phone parsing failed: ' . $e->getMessage());
        }
        // Return false if parsing fails
        return false;
    }
}

if (!function_exists('splitPhoneNumber')) {
    /**
     * Split an E.164 number into its country (ISO 3166-1 alpha-2) and the number as dialled within that country,
     * i.e. +66812345678 -> ['TH', '0812345678'], for showing a saved number in a country + number input
     * @param string|null $e164Number
     * @return array{0: string, 1: string} ['', ''] when empty; ['', the input] when it cannot be read
     */
    function splitPhoneNumber(?string $e164Number): array
    {
        $e164Number = trim((string) $e164Number);
        if ($e164Number === '') {
            return ['', ''];
        }
        $phoneUtil = PhoneNumberUtil::getInstance();
        try {
            $numberProto = $phoneUtil->parse($e164Number, null);
            $region      = (string) $phoneUtil->getRegionCodeForNumber($numberProto);
            if ($region !== '' && $region !== 'ZZ') {
                $national = $phoneUtil->format($numberProto, PhoneNumberFormat::NATIONAL);
                return [$region, preg_replace('/[^0-9]/', '', $national)];
            }
        } catch (NumberParseException $e) {
            log_message('debug', 'Phone parsing failed: ' . $e->getMessage());
        }
        return ['', $e164Number];
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
        $time = strtotime($date);
        if (false === $time) {
            return $date;
        }
        if ('th_TH' == $locale) {
            $month = date('n', $time);
            $monthNames = [
                'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
                'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
            ];
            return date('j', $time) . ' ' . $monthNames[$month - 1] . ' ' . (date('Y', $time) + 543);
        }
        return date('M j, Y', $time);
    }
}

if (!function_exists('localTime')) {
    /**
     * <time> tag for a UTC datetime from the API; main.js rewrites its text in the viewer's timezone.
     * The text before that (and without JS) is the UTC value.
     * @param string|null $utc e.g. '2026-11-01 08:30:00'
     * @return string
     */
    function localTime(?string $utc): string
    {
        if (empty($utc)) {
            return '';
        }
        return '<time data-utc datetime="' . esc($utc, 'attr') . '">' . esc($utc) . ' UTC</time>';
    }
}
