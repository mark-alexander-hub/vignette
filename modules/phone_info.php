<?php

/**
 * Vignette — Phone Info Module (Enhanced)
 * Parses and validates phone numbers, detects country/region from prefix,
 * identifies carrier/operator, checks messaging app presence,
 * cross-references spam databases, and formats numbers.
 *
 * Features:
 * 1. Carrier/operator detection (NumVerify API + local fallback)
 * 2. Number region detection (state/city from prefix)
 * 3. Messaging app presence (WhatsApp, Telegram)
 * 4. Spam/scam reputation check
 * 5. Multi-format output (E.164, national, international)
 */

class PhoneInfoModule {

    private string $numverifyKey;

    public function __construct(string $numverifyKey = '') {
        $this->numverifyKey = $numverifyKey;
    }

    /**
     * Country code prefix map: prefix => [country_name, iso2].
     */
    private function getCountryPrefixes(): array {
        return [
            '1684' => ['American Samoa', 'AS'],
            '1670' => ['Northern Mariana Islands', 'MP'],
            '1671' => ['Guam', 'GU'],
            '1787' => ['Puerto Rico', 'PR'],
            '1939' => ['Puerto Rico', 'PR'],
            '1340' => ['US Virgin Islands', 'VI'],
            '1242' => ['Bahamas', 'BS'],
            '1246' => ['Barbados', 'BB'],
            '1264' => ['Anguilla', 'AI'],
            '1268' => ['Antigua and Barbuda', 'AG'],
            '1284' => ['British Virgin Islands', 'VG'],
            '1345' => ['Cayman Islands', 'KY'],
            '1441' => ['Bermuda', 'BM'],
            '1473' => ['Grenada', 'GD'],
            '1649' => ['Turks and Caicos Islands', 'TC'],
            '1658' => ['Jamaica', 'JM'],
            '1664' => ['Montserrat', 'MS'],
            '1721' => ['Sint Maarten', 'SX'],
            '1758' => ['Saint Lucia', 'LC'],
            '1767' => ['Dominica', 'DM'],
            '1784' => ['Saint Vincent and the Grenadines', 'VC'],
            '1809' => ['Dominican Republic', 'DO'],
            '1829' => ['Dominican Republic', 'DO'],
            '1849' => ['Dominican Republic', 'DO'],
            '1868' => ['Trinidad and Tobago', 'TT'],
            '1869' => ['Saint Kitts and Nevis', 'KN'],
            '1876' => ['Jamaica', 'JM'],
            '44'   => ['United Kingdom', 'GB'],
            '91'   => ['India', 'IN'],
            '86'   => ['China', 'CN'],
            '81'   => ['Japan', 'JP'],
            '49'   => ['Germany', 'DE'],
            '33'   => ['France', 'FR'],
            '39'   => ['Italy', 'IT'],
            '34'   => ['Spain', 'ES'],
            '61'   => ['Australia', 'AU'],
            '55'   => ['Brazil', 'BR'],
            '52'   => ['Mexico', 'MX'],
            '7'    => ['Russia', 'RU'],
            '82'   => ['South Korea', 'KR'],
            '31'   => ['Netherlands', 'NL'],
            '46'   => ['Sweden', 'SE'],
            '47'   => ['Norway', 'NO'],
            '45'   => ['Denmark', 'DK'],
            '358'  => ['Finland', 'FI'],
            '48'   => ['Poland', 'PL'],
            '41'   => ['Switzerland', 'CH'],
            '43'   => ['Austria', 'AT'],
            '32'   => ['Belgium', 'BE'],
            '351'  => ['Portugal', 'PT'],
            '353'  => ['Ireland', 'IE'],
            '30'   => ['Greece', 'GR'],
            '36'   => ['Hungary', 'HU'],
            '420'  => ['Czech Republic', 'CZ'],
            '421'  => ['Slovakia', 'SK'],
            '40'   => ['Romania', 'RO'],
            '380'  => ['Ukraine', 'UA'],
            '90'   => ['Turkey', 'TR'],
            '966'  => ['Saudi Arabia', 'SA'],
            '971'  => ['United Arab Emirates', 'AE'],
            '972'  => ['Israel', 'IL'],
            '20'   => ['Egypt', 'EG'],
            '27'   => ['South Africa', 'ZA'],
            '234'  => ['Nigeria', 'NG'],
            '254'  => ['Kenya', 'KE'],
            '60'   => ['Malaysia', 'MY'],
            '65'   => ['Singapore', 'SG'],
            '66'   => ['Thailand', 'TH'],
            '62'   => ['Indonesia', 'ID'],
            '63'   => ['Philippines', 'PH'],
            '84'   => ['Vietnam', 'VN'],
            '64'   => ['New Zealand', 'NZ'],
            '56'   => ['Chile', 'CL'],
            '57'   => ['Colombia', 'CO'],
            '54'   => ['Argentina', 'AR'],
            '51'   => ['Peru', 'PE'],
            '58'   => ['Venezuela', 'VE'],
            '1'    => ['United States/Canada', 'US'],
        ];
    }

    private array $tollFreePrefixes = [
        '800', '888', '877', '866', '855', '844', '833',
    ];

    /**
     * Known spam/scam area codes and patterns.
     */
    private function getSpamIndicators(): array {
        return [
            // High-risk international prefixes often used in scam calls
            'high_risk_prefixes' => [
                '232' => 'Sierra Leone',
                '252' => 'Somalia',
                '225' => 'Ivory Coast',
                '233' => 'Ghana',
                '222' => 'Mauritania',
                '257' => 'Burundi',
                '263' => 'Zimbabwe',
                '676' => 'Tonga',
                '682' => 'Cook Islands',
                '216' => 'Tunisia',
            ],
            // Known robocall/telemarketing patterns
            'patterns' => [
                '/^1(900|976)/' => 'Premium rate number',
                '/^44(70)/' => 'UK personal number (often scam)',
                '/^44(843|844|845|870|871|872|873)/' => 'UK non-geographic rate (premium)',
            ],
        ];
    }

    /**
     * Main lookup — combines all enhanced features.
     */
    public function lookup(string $phone): array {
        $original = trim($phone);
        $hasPlus = str_starts_with($original, '+');
        $cleaned = preg_replace('/[^0-9]/', '', $original);

        if (strlen($cleaned) < 7 || strlen($cleaned) > 15) {
            return [
                'error' => 'Invalid phone number length — expected 7-15 digits',
                'original' => $original,
            ];
        }

        // Country detection
        $countryCode = '';
        $countryName = '';
        $countryIso = '';
        $nationalNumber = $cleaned;

        if ($hasPlus || strlen($cleaned) >= 10) {
            $prefixes = $this->getCountryPrefixes();
            foreach ($prefixes as $prefix => $info) {
                if (str_starts_with($cleaned, $prefix)) {
                    $countryCode = '+' . $prefix;
                    $countryName = $info[0];
                    $countryIso = $info[1];
                    $nationalNumber = substr($cleaned, strlen($prefix));
                    break;
                }
            }
        }

        if ($countryCode === '' && strlen($cleaned) === 10) {
            $countryCode = '+1';
            $countryName = 'United States/Canada';
            $countryIso = 'US';
            $nationalNumber = $cleaned;
        }

        if ($countryCode === '' && strlen($cleaned) === 11 && str_starts_with($cleaned, '1')) {
            $countryCode = '+1';
            $countryName = 'United States/Canada';
            $countryIso = 'US';
            $nationalNumber = substr($cleaned, 1);
        }

        $type = $this->detectType($countryCode, $nationalNumber, $cleaned);
        $formatted = $this->formatNumber($countryCode, $nationalNumber, $cleaned);
        $valid = strlen($cleaned) >= 7 && strlen($cleaned) <= 15 && $countryCode !== '';

        // NumVerify API — primary source for carrier, line type, location, validation
        $numverifyData = $this->queryNumVerify($cleaned);
        $carrier = '';
        $region = '';
        $numverifyValid = null;
        $internationalFormat = '';
        $localFormat = '';

        if ($numverifyData) {
            // NumVerify provides accurate, real-time data — use it as primary
            if (!empty($numverifyData['carrier'])) $carrier = $numverifyData['carrier'];
            if (!empty($numverifyData['line_type'])) $type = $numverifyData['line_type'];
            if (!empty($numverifyData['location'])) $region = $numverifyData['location'];
            if (!empty($numverifyData['country_name'])) $countryName = $numverifyData['country_name'];
            if (!empty($numverifyData['country_code'])) $countryIso = $numverifyData['country_code'];
            if (isset($numverifyData['valid'])) $numverifyValid = $numverifyData['valid'];
            if (!empty($numverifyData['international_format'])) $internationalFormat = $numverifyData['international_format'];
            if (!empty($numverifyData['local_format'])) $localFormat = $numverifyData['local_format'];

            // Override validation with API result
            if ($numverifyValid !== null) $valid = $numverifyValid;
        } else {
            // Fallback to local detection when API not available
            $region = $this->detectRegion($countryCode, $nationalNumber);
        }

        // These features work without API
        $spamCheck = $this->checkSpam($countryCode, $nationalNumber, $cleaned);
        $messaging = $this->checkMessagingApps($countryCode, $nationalNumber, $cleaned);
        $formats = $this->generateFormats($countryCode, $nationalNumber, $cleaned);

        // Merge API formats if available
        if ($internationalFormat) $formats['international'] = $internationalFormat;
        if ($localFormat) $formats['local'] = $localFormat;

        return [
            'original' => $original,
            'cleaned' => $cleaned,
            'formatted' => $internationalFormat ?: $formatted,
            'country_code' => $countryCode,
            'country' => $countryName,
            'country_iso' => $countryIso,
            'national_number' => $nationalNumber,
            'type' => $type,
            'valid' => $valid,
            'digits' => strlen($cleaned),
            'carrier' => $carrier,
            'region' => $region,
            'spam' => $spamCheck,
            'messaging' => $messaging,
            'formats' => $formats,
            'api_verified' => $numverifyData !== null,
        ];
    }

    /**
     * Feature 1: Carrier/operator detection.
     * Only returns data from verified API sources (NumVerify) or clearly labeled estimates.
     * Static prefix-based carrier guessing is unreliable due to number portability (MNP).
     */
    private function detectCarrier(string $countryCode, string $national, string $full): string {
        // Carrier detection requires API lookup due to number portability.
        // NumVerify or similar API will populate this field if configured.
        // We do NOT guess from prefixes — ported numbers make prefix-based detection unreliable.
        return '';
    }

    /**
     * Feature 2: Region detection from number prefix.
     * For landlines: area codes reliably map to regions.
     * For mobile: only shows region for countries without number portability across regions,
     * or where area codes are embedded in mobile numbers (like US).
     */
    private function detectRegion(string $countryCode, string $national): string {
        // India mobile: region detection from prefix is unreliable after MNP
        // Only show for landline numbers (starting with 0xx area codes) — skip for mobile
        if ($countryCode === '+91') {
            return ''; // MNP makes prefix-based region detection unreliable
        }

        // US/Canada area code to state/province — area codes are geographic and reliable
        if ($countryCode === '+1' && strlen($national) >= 3) {
            $areaCode = substr($national, 0, 3);
            $usRegions = [
                '201' => 'New Jersey', '202' => 'Washington DC', '203' => 'Connecticut',
                '206' => 'Washington (Seattle)', '207' => 'Maine', '208' => 'Idaho',
                '209' => 'California', '210' => 'Texas (San Antonio)', '212' => 'New York (Manhattan)',
                '213' => 'California (Los Angeles)', '214' => 'Texas (Dallas)', '215' => 'Pennsylvania (Philadelphia)',
                '216' => 'Ohio (Cleveland)', '217' => 'Illinois', '218' => 'Minnesota',
                '219' => 'Indiana', '224' => 'Illinois', '225' => 'Louisiana',
                '228' => 'Mississippi', '229' => 'Georgia', '231' => 'Michigan',
                '234' => 'Ohio', '239' => 'Florida', '240' => 'Maryland',
                '248' => 'Michigan', '251' => 'Alabama', '252' => 'North Carolina',
                '253' => 'Washington (Tacoma)', '254' => 'Texas', '256' => 'Alabama',
                '267' => 'Pennsylvania', '269' => 'Michigan', '270' => 'Kentucky',
                '272' => 'Pennsylvania', '276' => 'Virginia', '281' => 'Texas (Houston)',
                '301' => 'Maryland', '302' => 'Delaware', '303' => 'Colorado (Denver)',
                '304' => 'West Virginia', '305' => 'Florida (Miami)', '307' => 'Wyoming',
                '308' => 'Nebraska', '309' => 'Illinois', '310' => 'California (LA)',
                '312' => 'Illinois (Chicago)', '313' => 'Michigan (Detroit)', '314' => 'Missouri (St. Louis)',
                '315' => 'New York', '316' => 'Kansas', '317' => 'Indiana (Indianapolis)',
                '318' => 'Louisiana', '319' => 'Iowa', '320' => 'Minnesota',
                '321' => 'Florida', '323' => 'California (LA)', '325' => 'Texas',
                '330' => 'Ohio', '331' => 'Illinois', '334' => 'Alabama',
                '336' => 'North Carolina', '337' => 'Louisiana', '339' => 'Massachusetts',
                '347' => 'New York (NYC)', '351' => 'Massachusetts', '352' => 'Florida',
                '360' => 'Washington', '361' => 'Texas', '385' => 'Utah',
                '386' => 'Florida', '401' => 'Rhode Island', '402' => 'Nebraska',
                '404' => 'Georgia (Atlanta)', '405' => 'Oklahoma', '406' => 'Montana',
                '407' => 'Florida (Orlando)', '408' => 'California (San Jose)',
                '409' => 'Texas', '410' => 'Maryland (Baltimore)', '412' => 'Pennsylvania (Pittsburgh)',
                '413' => 'Massachusetts', '414' => 'Wisconsin (Milwaukee)', '415' => 'California (San Francisco)',
                '417' => 'Missouri', '419' => 'Ohio', '423' => 'Tennessee',
                '424' => 'California', '425' => 'Washington (Bellevue)',
                '430' => 'Texas', '432' => 'Texas', '434' => 'Virginia',
                '440' => 'Ohio', '442' => 'California', '443' => 'Maryland',
                '469' => 'Texas (Dallas)', '470' => 'Georgia', '475' => 'Connecticut',
                '478' => 'Georgia', '479' => 'Arkansas', '480' => 'Arizona (Phoenix)',
                '484' => 'Pennsylvania', '501' => 'Arkansas', '502' => 'Kentucky',
                '503' => 'Oregon (Portland)', '504' => 'Louisiana (New Orleans)',
                '505' => 'New Mexico', '507' => 'Minnesota', '508' => 'Massachusetts',
                '509' => 'Washington', '510' => 'California (Oakland)', '512' => 'Texas (Austin)',
                '513' => 'Ohio (Cincinnati)', '515' => 'Iowa', '516' => 'New York (Long Island)',
                '517' => 'Michigan', '518' => 'New York', '520' => 'Arizona (Tucson)',
                '530' => 'California', '531' => 'Nebraska', '534' => 'Wisconsin',
                '539' => 'Oklahoma', '540' => 'Virginia', '541' => 'Oregon',
                '551' => 'New Jersey', '559' => 'California', '561' => 'Florida',
                '562' => 'California', '563' => 'Iowa', '567' => 'Ohio',
                '570' => 'Pennsylvania', '571' => 'Virginia', '573' => 'Missouri',
                '574' => 'Indiana', '575' => 'New Mexico', '580' => 'Oklahoma',
                '585' => 'New York (Rochester)', '586' => 'Michigan',
                '601' => 'Mississippi', '602' => 'Arizona (Phoenix)', '603' => 'New Hampshire',
                '605' => 'South Dakota', '606' => 'Kentucky', '607' => 'New York',
                '608' => 'Wisconsin', '609' => 'New Jersey', '610' => 'Pennsylvania',
                '612' => 'Minnesota (Minneapolis)', '614' => 'Ohio (Columbus)',
                '615' => 'Tennessee (Nashville)', '616' => 'Michigan',
                '617' => 'Massachusetts (Boston)', '618' => 'Illinois',
                '619' => 'California (San Diego)', '620' => 'Kansas',
                '623' => 'Arizona', '626' => 'California', '628' => 'California',
                '629' => 'Tennessee', '630' => 'Illinois', '631' => 'New York',
                '636' => 'Missouri', '641' => 'Iowa', '646' => 'New York (Manhattan)',
                '650' => 'California', '651' => 'Minnesota (St. Paul)',
                '657' => 'California', '660' => 'Missouri',
                '661' => 'California', '662' => 'Mississippi',
                '667' => 'Maryland', '669' => 'California (San Jose)',
                '678' => 'Georgia (Atlanta)', '681' => 'West Virginia',
                '682' => 'Texas', '701' => 'North Dakota', '702' => 'Nevada (Las Vegas)',
                '703' => 'Virginia (Arlington)', '704' => 'North Carolina (Charlotte)',
                '706' => 'Georgia', '707' => 'California', '708' => 'Illinois',
                '712' => 'Iowa', '713' => 'Texas (Houston)', '714' => 'California',
                '715' => 'Wisconsin', '716' => 'New York (Buffalo)', '717' => 'Pennsylvania',
                '718' => 'New York (NYC)', '719' => 'Colorado', '720' => 'Colorado (Denver)',
                '724' => 'Pennsylvania', '725' => 'Nevada', '727' => 'Florida',
                '731' => 'Tennessee', '732' => 'New Jersey',
                '734' => 'Michigan', '737' => 'Texas (Austin)',
                '740' => 'Ohio', '743' => 'North Carolina',
                '747' => 'California', '754' => 'Florida',
                '757' => 'Virginia', '760' => 'California',
                '762' => 'Georgia', '763' => 'Minnesota',
                '765' => 'Indiana', '769' => 'Mississippi',
                '770' => 'Georgia (Atlanta)', '772' => 'Florida',
                '773' => 'Illinois (Chicago)', '774' => 'Massachusetts',
                '775' => 'Nevada', '779' => 'Illinois',
                '781' => 'Massachusetts', '785' => 'Kansas',
                '786' => 'Florida (Miami)', '801' => 'Utah',
                '802' => 'Vermont', '803' => 'South Carolina',
                '804' => 'Virginia (Richmond)', '805' => 'California',
                '806' => 'Texas', '808' => 'Hawaii',
                '810' => 'Michigan', '812' => 'Indiana',
                '813' => 'Florida (Tampa)', '814' => 'Pennsylvania',
                '815' => 'Illinois', '816' => 'Missouri (Kansas City)',
                '817' => 'Texas (Fort Worth)', '818' => 'California (LA)',
                '828' => 'North Carolina', '830' => 'Texas',
                '831' => 'California', '832' => 'Texas (Houston)',
                '843' => 'South Carolina', '845' => 'New York',
                '847' => 'Illinois', '848' => 'New Jersey',
                '850' => 'Florida', '856' => 'New Jersey',
                '857' => 'Massachusetts (Boston)', '858' => 'California (San Diego)',
                '859' => 'Kentucky', '860' => 'Connecticut',
                '862' => 'New Jersey', '863' => 'Florida',
                '864' => 'South Carolina', '865' => 'Tennessee',
                '870' => 'Arkansas', '872' => 'Illinois',
                '878' => 'Pennsylvania', '901' => 'Tennessee (Memphis)',
                '903' => 'Texas', '904' => 'Florida (Jacksonville)',
                '906' => 'Michigan', '907' => 'Alaska',
                '908' => 'New Jersey', '909' => 'California',
                '910' => 'North Carolina', '912' => 'Georgia',
                '913' => 'Kansas', '914' => 'New York',
                '915' => 'Texas (El Paso)', '916' => 'California (Sacramento)',
                '917' => 'New York (NYC)', '918' => 'Oklahoma',
                '919' => 'North Carolina (Raleigh)', '920' => 'Wisconsin',
                '925' => 'California', '928' => 'Arizona',
                '929' => 'New York (NYC)', '931' => 'Tennessee',
                '936' => 'Texas', '937' => 'Ohio',
                '938' => 'Alabama', '940' => 'Texas',
                '941' => 'Florida', '947' => 'Michigan',
                '949' => 'California', '951' => 'California',
                '952' => 'Minnesota', '954' => 'Florida (Fort Lauderdale)',
                '956' => 'Texas', '959' => 'Connecticut',
                '970' => 'Colorado', '971' => 'Oregon',
                '972' => 'Texas (Dallas)', '973' => 'New Jersey',
                '978' => 'Massachusetts', '979' => 'Texas',
                '980' => 'North Carolina', '984' => 'North Carolina',
                '985' => 'Louisiana',
            ];
            if (isset($usRegions[$areaCode])) return $usRegions[$areaCode];
        }

        // UK landline area codes are geographic and reliable (mobile 07xx are not)
        if ($countryCode === '+44' && strlen($national) >= 2 && !str_starts_with($national, '7')) {
            $ukPrefix = substr($national, 0, 2);
            $ukRegions = [
                '20' => 'London', '21' => 'Birmingham', '22' => 'Southampton',
                '23' => 'Portsmouth / Southampton', '24' => 'Coventry',
                '28' => 'Northern Ireland', '29' => 'Cardiff',
                '11' => 'Sheffield / Nottingham', '12' => 'Edinburgh',
                '13' => 'Newcastle / Leeds', '14' => 'Manchester / Liverpool',
                '15' => 'Bristol / Bath', '16' => 'Leicester',
                '17' => 'Plymouth / Exeter', '18' => 'Reading / Bournemouth',
                '19' => 'Wolverhampton / Stoke',
            ];
            if (isset($ukRegions[$ukPrefix])) return $ukRegions[$ukPrefix];
        }

        return '';
    }

    /**
     * Feature 3: Check messaging app presence.
     */
    private function checkMessagingApps(string $countryCode, string $national, string $full): array {
        $e164 = $countryCode . $national;
        $apps = [];

        // WhatsApp: check via their public API endpoint
        $waUrl = 'https://wa.me/' . ltrim($e164, '+');
        $apps['whatsapp'] = [
            'check_url' => $waUrl,
            'note' => 'Click to verify if this number has WhatsApp',
        ];

        // Telegram: check via public resolve endpoint
        $tgUrl = 'https://t.me/' . ltrim($e164, '+');
        $apps['telegram'] = [
            'check_url' => $tgUrl,
            'note' => 'Click to check Telegram presence',
        ];

        return $apps;
    }

    /**
     * Feature 4: Spam/scam reputation check.
     */
    private function checkSpam(string $countryCode, string $national, string $full): array {
        $result = [
            'risk_level' => 'unknown',
            'flags' => [],
        ];

        $indicators = $this->getSpamIndicators();
        $ccDigits = ltrim($countryCode, '+');

        // Check high-risk country prefixes
        if (isset($indicators['high_risk_prefixes'][$ccDigits])) {
            $result['flags'][] = 'High-risk country prefix (' . $indicators['high_risk_prefixes'][$ccDigits] . ') — often used in phone scams';
            $result['risk_level'] = 'high';
        }

        // Check known patterns
        foreach ($indicators['patterns'] as $pattern => $desc) {
            if (preg_match($pattern, $full)) {
                $result['flags'][] = $desc;
                $result['risk_level'] = $result['risk_level'] === 'high' ? 'high' : 'medium';
            }
        }

        // Toll-free numbers are usually legitimate businesses
        if (in_array($countryCode, ['+1']) && in_array(substr($national, 0, 3), $this->tollFreePrefixes)) {
            $result['flags'][] = 'Toll-free number — typically used by businesses';
            if ($result['risk_level'] === 'unknown') $result['risk_level'] = 'low';
        }

        // Very short national numbers can be premium rate
        if (strlen($national) <= 4) {
            $result['flags'][] = 'Short code — may be premium rate or service number';
            $result['risk_level'] = 'medium';
        }

        if (empty($result['flags'])) {
            $result['risk_level'] = 'none';
            $result['flags'][] = 'No spam indicators detected';
        }

        return $result;
    }

    /**
     * Feature 5: Generate multiple standard formats.
     */
    private function generateFormats(string $countryCode, string $national, string $full): array {
        $e164 = $countryCode . $national;
        $formats = [
            'e164' => $e164,
            'international' => $this->formatNumber($countryCode, $national, $full),
            'national' => $national,
        ];

        // NANP specific
        if ($countryCode === '+1' && strlen($national) === 10) {
            $formats['national'] = sprintf('(%s) %s-%s',
                substr($national, 0, 3), substr($national, 3, 3), substr($national, 6, 4));
            $formats['rfc3966'] = 'tel:' . $e164;
        } else {
            $formats['rfc3966'] = 'tel:' . $e164;
        }

        return $formats;
    }

    /**
     * NumVerify API — real-time phone number validation and intelligence.
     * Returns: valid, number, local_format, international_format, country_prefix,
     *          country_code, country_name, location, carrier, line_type
     */
    private function queryNumVerify(string $number): ?array {
        if (empty($this->numverifyKey)) return null;

        $url = 'http://apilayer.net/api/validate?' . http_build_query([
            'access_key' => $this->numverifyKey,
            'number' => $number,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError || $status !== 200 || !$response) return null;

        $data = json_decode($response, true);

        // Check for API error
        if (isset($data['error'])) {
            error_log('NumVerify error: ' . ($data['error']['info'] ?? 'Unknown'));
            return null;
        }

        if (empty($data)) return null;

        // Return ALL fields from NumVerify
        return [
            'valid'                => $data['valid'] ?? false,
            'number'               => $data['number'] ?? '',
            'local_format'         => $data['local_format'] ?? '',
            'international_format' => $data['international_format'] ?? '',
            'country_prefix'       => $data['country_prefix'] ?? '',
            'country_code'         => $data['country_code'] ?? '',
            'country_name'         => $data['country_name'] ?? '',
            'location'             => $data['location'] ?? '',
            'carrier'              => $data['carrier'] ?? '',
            'line_type'            => $data['line_type'] ?? '',
        ];
    }

    /**
     * Detect number type: mobile, landline, toll-free, or unknown.
     */
    private function detectType(string $countryCode, string $national, string $full): string {
        if (in_array($countryCode, ['+1', ''])) {
            $areaCode = substr($national, 0, 3);
            if (in_array($areaCode, $this->tollFreePrefixes)) return 'toll-free';
        }
        if ($countryCode === '+44' && str_starts_with($national, '7') && strlen($national) === 10) return 'mobile';
        if ($countryCode === '+44' && preg_match('/^[12]/', $national)) return 'landline';
        if ($countryCode === '+91' && strlen($national) === 10 && preg_match('/^[6-9]/', $national)) return 'mobile';
        if ($countryCode === '+49' && preg_match('/^1[567]/', $national)) return 'mobile';
        if ($countryCode === '+61' && str_starts_with($national, '4')) return 'mobile';
        return 'unknown';
    }

    /**
     * Format number in a human-readable way.
     */
    private function formatNumber(string $countryCode, string $national, string $full): string {
        if ($countryCode === '+1' && strlen($national) === 10) {
            return sprintf('+1 (%s) %s-%s', substr($national, 0, 3), substr($national, 3, 3), substr($national, 6, 4));
        }
        if ($countryCode === '+44' && strlen($national) >= 10) {
            return sprintf('+44 %s %s', substr($national, 0, 4), substr($national, 4));
        }
        if ($countryCode === '+91' && strlen($national) === 10) {
            return sprintf('+91 %s %s %s', substr($national, 0, 5), substr($national, 5, 5), '');
        }
        if ($countryCode !== '') return $countryCode . ' ' . $national;
        return $full;
    }

    /**
     * Normalize raw lookup data into Vignette's standard format.
     */
    public function normalize(array $data): array {
        if (isset($data['error'])) {
            return [
                'source' => 'phone_info',
                'status' => 'error',
                'error'  => $data['error'],
                'data'   => ['original' => $data['original'] ?? ''],
            ];
        }

        return [
            'source' => 'phone_info',
            'status' => 'success',
            'data'   => [
                'number'          => $data['cleaned'],
                'formatted'       => $data['formatted'],
                'country_code'    => $data['country_code'],
                'country'         => $data['country'],
                'country_iso'     => $data['country_iso'],
                'national_number' => $data['national_number'],
                'type'            => $data['type'],
                'valid'           => $data['valid'],
                'digits'          => $data['digits'],
                'carrier'         => $data['carrier'] ?? '',
                'region'          => $data['region'] ?? '',
                'spam'            => $data['spam'] ?? [],
                'messaging'       => $data['messaging'] ?? [],
                'formats'         => $data['formats'] ?? [],
                'api_verified'    => $data['api_verified'] ?? false,
            ],
        ];
    }
}
