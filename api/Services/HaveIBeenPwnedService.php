<?php

class HaveIBeenPwnedService {

    private $apiKey;

    public function __construct() {
        $this->apiKey = '00000000000000000000000000000000'; // test key
    }

    public function checkBreaches($email) {

        $url = "https://haveibeenpwned.com/api/v3/breachedaccount/" 
               . urlencode($email) 
               . "?truncateResponse=false";

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'hibp-api-key: ' . $this->apiKey,
                'User-Agent: Vignette-Platform'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status === 404) {
            return [];
        }

        if ($status === 401 || $status === 403) {
            return ["error" => "Authorization or user-agent issue"];
        }

        return json_decode($response, true) ?? [];
    }
}