<?php

/**
 * Vignette — IPInfo Module
 * IP geolocation, ISP, organization, and VPN/proxy detection.
 * API docs: https://ipinfo.io/developers
 */

class IpInfoModule {

    private string $token;
    private string $baseUrl = 'https://ipinfo.io';

    public function __construct(string $token) {
        $this->token = $token;
    }

    /**
     * Lookup full details for an IP address.
     */
    public function lookup(string $ip): array {
        if (empty($this->token)) {
            return ['error' => 'IPInfo token not configured — get one at ipinfo.io/signup'];
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['error' => 'Invalid IP address'];
        }

        $url = $this->baseUrl . '/' . $ip . '?token=' . $this->token;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: Vignette-OSINT-Platform'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 8
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            return ['error' => "IPInfo API returned status $status"];
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Normalize IPInfo data into Vignette's standard format.
     */
    public function normalize(array $data): array {
        if (isset($data['error'])) {
            return [
                'source' => 'ipinfo',
                'status' => 'error',
                'error' => $data['error'],
                'data' => []
            ];
        }

        // Parse lat/lon from "loc" field (format: "lat,lon")
        $lat = null;
        $lon = null;
        if (!empty($data['loc'])) {
            $parts = explode(',', $data['loc']);
            if (count($parts) === 2) {
                $lat = (float)$parts[0];
                $lon = (float)$parts[1];
            }
        }

        return [
            'source' => 'ipinfo',
            'status' => 'success',
            'data' => [
                'ip' => $data['ip'] ?? '',
                'hostname' => $data['hostname'] ?? '',
                'city' => $data['city'] ?? '',
                'region' => $data['region'] ?? '',
                'country' => $data['country'] ?? '',
                'latitude' => $lat,
                'longitude' => $lon,
                'org' => $data['org'] ?? '',
                'postal' => $data['postal'] ?? '',
                'timezone' => $data['timezone'] ?? '',
                'is_vpn' => !empty($data['privacy']['vpn']),
                'is_proxy' => !empty($data['privacy']['proxy']),
                'is_tor' => !empty($data['privacy']['tor']),
                'is_hosting' => !empty($data['privacy']['hosting']),
            ]
        ];
    }
}
