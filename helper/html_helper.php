<?php

use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;

if (!function_exists('planName')) {
    /**
     * A plan's name in the UI language (BusinessPlans.plan-names), or its code when it has none
     * @param string $code plan_master.plan_code
     * @return string
     */
    function planName(string $code): string
    {
        $key  = 'BusinessPlans.plan-names.' . $code;
        $name = lang($key);
        return $name === $key ? ucfirst($code) : $name;
    }
}

if (!function_exists('formatMoney')) {
    /**
     * An amount from the API ("299.00") in its currency: as formatCurrency() writes it when it knows the currency,
     * else "EUR 299.00"; '' when it is not a number
     * @param mixed  $amount
     * @param string $currencyCode
     * @return string
     */
    function formatMoney(mixed $amount, string $currencyCode): string
    {
        if (null === $amount || ! is_numeric($amount)) {
            return '';
        }
        $text = formatCurrency((float) $amount, $currencyCode);
        return str_starts_with($text, '[') ? trim($currencyCode . ' ' . number_format((float) $amount, 2)) : $text;
    }
}

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

if (!function_exists('formatTime')) {
    /**
     * Format date according to the locale
     * @param $time (accepts only HH:MM)
     * @param $locale
     * @return false|string
     */
    function formatTime($time, $locale): false|string
    {
        $locale = match ($locale) {
            'th' => 'th_TH',
            default => 'en_US',
        };
        // dummy date
        $dt   = '2026-01-01 ' . $time . ':00';
        $time = strtotime($dt);
        if (false === $time) {
            return $time;
        }
        if ('th_TH' == $locale) {
            return date('H:i', $time) . ' น.';
        }
        return date('g:i a', $time);
    }
}

if (!function_exists('formatMinutes')) {
    /**
     * A length in minutes as hours and minutes, leaving out a part that is zero:
     * 90 => '1h 30m', 120 => '2h', 45 => '45m' (th: '1 ชม. 30 นาที', '2 ชม.', '45 นาที')
     * @param int|string|null $minutes
     * @param string|null     $locale
     * @return string '' when $minutes is empty or not a number
     */
    function formatMinutes(int|string|null $minutes, ?string $locale): string
    {
        if (null === $minutes || !is_numeric($minutes)) {
            return '';
        }
        $minutes = max(0, (int) $minutes);
        $hours   = intdiv($minutes, 60);
        $minutes %= 60;
        [$h, $m] = 'th' === $locale ? ['%d ชม.', '%d นาที'] : ['%dh', '%dm'];
        $parts = [];
        if ($hours > 0) {
            $parts[] = sprintf($h, $hours);
        }
        if ($minutes > 0 || 0 === $hours) {
            $parts[] = sprintf($m, $minutes);
        }
        return implode(' ', $parts);
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
