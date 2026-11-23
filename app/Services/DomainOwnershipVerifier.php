<?php

namespace App\Services;

class DomainOwnershipVerifier
{
    public function verify(string $host, string $token): bool
    {
        $records = dns_get_record('_leadradar-verification.'.$host, DNS_TXT);

        if (! is_array($records)) {
            return false;
        }

        $expectedValue = 'leadradar-verification='.$token;

        foreach ($records as $record) {
            $value = $record['txt'] ?? null;

            if (! is_string($value) && is_array($record['entries'] ?? null)) {
                $value = implode('', array_filter($record['entries'], 'is_string'));
            }

            if (is_string($value) && hash_equals($expectedValue, trim($value))) {
                return true;
            }
        }

        return false;
    }
}
