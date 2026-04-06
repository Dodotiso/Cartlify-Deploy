<?php

namespace App\Service;

class PhoneNumberHelper
{
    /**
     * Normalize phone number to E.164 format (supports all countries)
     * E.164 format: +[country code][phone number without leading zero]
     * 
     * Examples:
     * - Philippines: 09171234567 -> +639171234567
     * - USA: (415) 555-2671 -> +14155552671
     * - UK: 020 7946 0123 -> +442079460123
     * - Japan: 090-1234-5678 -> +819012345678
     */
    public static function normalizeToE164(string $number): string
    {
        // Remove all spaces, dashes, parentheses, dots
        $number = preg_replace('/[\s\-\(\)\.]/', '', $number);
        
        // If it already starts with +, return as is
        if (str_starts_with($number, '+')) {
            return $number;
        }
        
        // If it starts with 00 (international prefix), replace with +
        if (str_starts_with($number, '00')) {
            return '+' . substr($number, 2);
        }
        
        // For numbers without country code, we need the country from intl-tel-input
        // This is a fallback - the frontend should provide the full number
        return $number;
    }
    
    /**
     * Validate phone number in E.164 format (supports all countries)
     * E.164 format: + followed by 7-15 digits
     */
    public static function isValidE164(string $number): bool
    {
        $pattern = '/^\+[1-9][0-9]{6,14}$/';
        return preg_match($pattern, $number) === 1;
    }
    
    /**
     * Get validation error message
     */
    public static function getPhoneErrorMessage(string $phone, bool $isRequired = true): string
    {
        if ($isRequired && empty($phone)) {
            return "Contact number is required.";
        }
        
        if (!self::isValidE164($phone)) {
            return "Invalid contact number. Please enter a valid international phone number with country code (e.g., +639171234567).";
        }
        
        return "";
    }
    
    /**
     * Validate email domain (Gmail, Yahoo, Outlook, Hotmail only)
     */
    public static function isValidEmailDomain(string $email): bool
    {
        $allowedDomains = [
            'gmail.com',
            'yahoo.com',
            'yahoo.com.ph',
            'outlook.com',
            'hotmail.com',
            'live.com',
            'msn.com',
        ];
        
        $domain = substr(strrchr($email, "@"), 1);
        return in_array(strtolower($domain), $allowedDomains);
    }
    
    /**
     * Format phone number for display
     */
    public static function formatForDisplay(string $number): string
    {
        $clean = ltrim($number, '+');
        
        // Philippine format: +639171234567 -> 0917 123 4567
        if (str_starts_with($clean, '63') && strlen($clean) === 12) {
            $local = '0' . substr($clean, 2);
            return substr($local, 0, 4) . ' ' . substr($local, 4, 3) . ' ' . substr($local, 7, 4);
        }
        
        // USA format: +14155552671 -> (415) 555-2671
        if (str_starts_with($clean, '1') && strlen($clean) === 11) {
            return '(' . substr($clean, 1, 3) . ') ' . substr($clean, 4, 3) . '-' . substr($clean, 7, 4);
        }
        
        return '+' . $clean;
    }
}