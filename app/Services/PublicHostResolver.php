<?php

namespace App\Services;

use App\Exceptions\WebsiteAuditException;

class PublicHostResolver
{
    public function resolve(string $host): string
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        if (! is_array($records)) {
            throw new WebsiteAuditException('Could not resolve this website domain.');
        }

        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        if ($addresses === []) {
            throw new WebsiteAuditException('This domain does not resolve to a public website.');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new WebsiteAuditException('Only publicly accessible websites can be analyzed.');
            }
        }

        return $addresses[0];
    }
}
