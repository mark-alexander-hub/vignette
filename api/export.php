<?php

/**
 * Vignette — PDF Export (Printable HTML Report)
 *
 * Opens a print-friendly HTML page from stored search data.
 * The browser's native print-to-PDF handles the actual conversion.
 *
 * Usage: /vignette/api/export.php?search_id=123
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Database.php';

// ---------------------------------------------------------------------------
// Validate input
// ---------------------------------------------------------------------------
$searchId = isset($_GET['search_id']) ? (int)$_GET['search_id'] : 0;
if ($searchId <= 0) {
    http_response_code(400);
    echo 'Missing or invalid search_id parameter.';
    exit;
}

// ---------------------------------------------------------------------------
// Load data from database
// ---------------------------------------------------------------------------
try {
    $db  = new Database();
    $pdo = $db->getConnection();

    // Search record
    $stmt = $pdo->prepare("SELECT * FROM searches WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $searchId]);
    $search = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$search) {
        http_response_code(404);
        echo 'Search not found.';
        exit;
    }

    // Profile
    $stmt = $pdo->prepare("SELECT * FROM profiles WHERE search_id = :id LIMIT 1");
    $stmt->execute([':id' => $searchId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    // Raw data sources
    $stmt = $pdo->prepare("SELECT source_name, raw_data, status FROM data_sources WHERE search_id = :id");
    $stmt->execute([':id' => $searchId]);
    $sources = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sources[$row['source_name']] = [
            'data'   => json_decode($row['raw_data'], true) ?: [],
            'status' => $row['status'],
        ];
    }

    // Intelligence report (optional)
    $stmt = $pdo->prepare("SELECT * FROM intelligence_reports WHERE search_id = :id LIMIT 1");
    $stmt->execute([':id' => $searchId]);
    $intelReport = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (\Throwable $e) {
    http_response_code(500);
    error_log('Vignette export error: ' . $e->getMessage());
    echo 'An error occurred while generating the report. Please try again.';
    exit;
}

// ---------------------------------------------------------------------------
// Helper: safe HTML output
// ---------------------------------------------------------------------------
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sectionHeader(string $title): string {
    return '<h2 class="section-title">' . e($title) . '</h2>';
}

function dataRow(string $label, ?string $value): string {
    if ($value === null || $value === '') return '';
    return '<div class="data-row"><span class="data-label">' . e($label) . '</span><span class="data-value">' . e($value) . '</span></div>';
}

function riskColor(int $score): string {
    if ($score > 75) return '#ef4444';
    if ($score > 50) return '#f97316';
    if ($score > 20) return '#eab308';
    return '#22c55e';
}

// ---------------------------------------------------------------------------
// Prepare display values
// ---------------------------------------------------------------------------
$queryValue = $search['query_value'] ?? '';
$queryType  = $search['query_type'] ?? '';
$createdAt  = $search['created_at'] ?? '';

$displayName    = $profile['display_name'] ?? '';
$avatarUrl      = $profile['avatar_url'] ?? '';
$location       = $profile['location'] ?? '';
$bio            = $profile['bio'] ?? '';
$knownEmails    = json_decode($profile['known_emails'] ?? '[]', true) ?: [];
$knownUsernames = json_decode($profile['known_usernames'] ?? '[]', true) ?: [];
$socialLinks    = json_decode($profile['social_links'] ?? '[]', true) ?: [];
$aiSummary      = $profile['ai_summary'] ?? '';
$riskScore      = (int)($profile['risk_score'] ?? 0);

$now = date('Y-m-d H:i:s T');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vignette Report — <?= e($queryValue) ?></title>
    <style>
        /* ===== Reset & Base ===== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.55;
            color: #1a1a2e;
            background: #fff;
            padding: 0;
        }

        .report {
            max-width: 800px;
            margin: 0 auto;
            padding: 32px 40px;
        }

        /* ===== Header ===== */
        .report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid #0ea5e9;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .report-header .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .report-header .brand-name {
            font-size: 22pt;
            font-weight: 800;
            letter-spacing: 3px;
            color: #0ea5e9;
        }
        .report-header .brand-tagline {
            font-size: 8pt;
            color: #64748b;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .report-header .report-meta {
            text-align: right;
            font-size: 9pt;
            color: #64748b;
        }

        /* ===== Query Banner ===== */
        .query-banner {
            background: #f1f5f9;
            border-left: 4px solid #0ea5e9;
            padding: 12px 16px;
            margin-bottom: 24px;
            border-radius: 0 6px 6px 0;
        }
        .query-banner .query-type {
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            margin-bottom: 2px;
        }
        .query-banner .query-value {
            font-size: 14pt;
            font-weight: 700;
            color: #1a1a2e;
            word-break: break-all;
        }

        /* ===== Risk Badge ===== */
        .risk-badge-large {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 11pt;
            color: #fff;
        }

        /* ===== Profile Card ===== */
        .profile-card {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 24px;
            padding: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .profile-card img {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }
        .profile-card .profile-info { flex: 1; }
        .profile-card .profile-name { font-size: 14pt; font-weight: 700; }
        .profile-card .profile-bio { color: #475569; font-size: 10pt; margin-top: 4px; }
        .profile-card .profile-location { color: #64748b; font-size: 9pt; margin-top: 2px; }

        /* ===== Sections ===== */
        .section {
            margin-bottom: 22px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 12pt;
            font-weight: 700;
            color: #0ea5e9;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 6px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ===== Data Rows ===== */
        .data-row {
            display: flex;
            padding: 4px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 10pt;
        }
        .data-label {
            width: 160px;
            flex-shrink: 0;
            font-weight: 600;
            color: #475569;
        }
        .data-value {
            flex: 1;
            color: #1a1a2e;
            word-break: break-word;
        }

        /* ===== Tags ===== */
        .tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 8pt;
            font-weight: 600;
            margin: 2px 2px;
        }
        .tag-safe { background: #dcfce7; color: #166534; }
        .tag-danger { background: #fef2f2; color: #dc2626; }
        .tag-warning { background: #fefce8; color: #a16207; }
        .tag-info { background: #eff6ff; color: #1d4ed8; }
        .tag-muted { background: #f1f5f9; color: #475569; }

        /* ===== Breach Items ===== */
        .breach-item {
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .breach-item:last-child { border-bottom: none; }
        .breach-name { font-weight: 600; font-size: 10pt; }
        .breach-meta { font-size: 8.5pt; color: #64748b; margin-top: 2px; }
        .breach-classes { margin-top: 4px; }

        /* ===== AI Summary ===== */
        .ai-summary {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            font-size: 10pt;
            line-height: 1.65;
            color: #334155;
            white-space: pre-wrap;
        }

        /* ===== Link list ===== */
        .link-list a {
            color: #0ea5e9;
            text-decoration: none;
            font-size: 9.5pt;
        }
        .link-list a:hover { text-decoration: underline; }

        /* ===== Footer ===== */
        .report-footer {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 2px solid #e2e8f0;
            text-align: center;
            font-size: 8.5pt;
            color: #94a3b8;
        }
        .report-footer strong { color: #0ea5e9; }

        /* ===== Print Button (screen only) ===== */
        .print-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #1a1a2e;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            z-index: 9999;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .print-bar button {
            background: #0ea5e9;
            color: #fff;
            border: none;
            padding: 8px 24px;
            border-radius: 6px;
            font-size: 10pt;
            font-weight: 600;
            cursor: pointer;
        }
        .print-bar button:hover { background: #0284c7; }
        .print-bar span {
            color: #94a3b8;
            font-size: 9pt;
        }

        /* ===== Print Styles ===== */
        @media print {
            .print-bar { display: none !important; }
            body { padding: 0; }
            .report { padding: 16px 20px; max-width: none; }
            .section { page-break-inside: avoid; }
            .report-header { page-break-after: avoid; }
        }

        /* Push content below fixed print bar on screen */
        @media screen {
            .report { margin-top: 56px; }
        }
    </style>
</head>
<body>

<!-- Screen-only print bar -->
<div class="print-bar">
    <button onclick="window.print()">Download as PDF</button>
    <span>Use your browser's print dialog to save as PDF</span>
</div>

<div class="report">

    <!-- ===== Header ===== -->
    <div class="report-header">
        <div class="brand">
            <svg width="36" height="36" viewBox="0 0 32 32" fill="none">
                <circle cx="16" cy="16" r="14" stroke="#0ea5e9" stroke-width="2"/>
                <circle cx="16" cy="16" r="8" stroke="#0ea5e9" stroke-width="1.5" stroke-dasharray="3 3"/>
                <circle cx="16" cy="16" r="3" fill="#0ea5e9"/>
            </svg>
            <div>
                <div class="brand-name">VIGNETTE</div>
                <div class="brand-tagline">AI-Powered Digital Intelligence</div>
            </div>
        </div>
        <div class="report-meta">
            OSINT Report<br>
            Searched: <?= e($createdAt) ?><br>
            Exported: <?= e($now) ?>
        </div>
    </div>

    <!-- ===== Query Banner ===== -->
    <div class="query-banner">
        <div class="query-type"><?= e(strtoupper($queryType)) ?> Search</div>
        <div class="query-value"><?= e($queryValue) ?></div>
    </div>

    <!-- ===== Risk Score ===== -->
    <?php if ($profile): ?>
    <div style="margin-bottom: 20px;">
        <span class="risk-badge-large" style="background:<?= riskColor($riskScore) ?>">
            Risk Score: <?= $riskScore ?>/100
        </span>
    </div>
    <?php endif; ?>

    <!-- ===== Profile Identity ===== -->
    <?php if ($displayName || $avatarUrl): ?>
    <div class="profile-card">
        <?php if ($avatarUrl): ?>
            <img src="<?= e($avatarUrl) ?>" alt="Avatar">
        <?php endif; ?>
        <div class="profile-info">
            <?php if ($displayName): ?>
                <div class="profile-name"><?= e($displayName) ?></div>
            <?php endif; ?>
            <?php if ($bio): ?>
                <div class="profile-bio"><?= e($bio) ?></div>
            <?php endif; ?>
            <?php if ($location): ?>
                <div class="profile-location"><?= e($location) ?></div>
            <?php endif; ?>
            <?php if ($knownEmails): ?>
                <div style="margin-top:6px;font-size:9pt;color:#475569">
                    <strong>Emails:</strong> <?= e(implode(', ', $knownEmails)) ?>
                </div>
            <?php endif; ?>
            <?php if ($knownUsernames): ?>
                <div style="font-size:9pt;color:#475569">
                    <strong>Usernames:</strong> <?= e(implode(', ', $knownUsernames)) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== Social Links ===== -->
    <?php if (!empty($socialLinks)): ?>
    <div class="section">
        <?= sectionHeader('Social Profiles') ?>
        <div class="link-list">
            <?php foreach ($socialLinks as $platform => $url): ?>
                <?php if ($url): ?>
                    <div class="data-row">
                        <span class="data-label"><?= e(ucfirst($platform)) ?></span>
                        <span class="data-value"><a href="<?= e($url) ?>" target="_blank"><?= e($url) ?></a></span>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== AI Intelligence Summary ===== -->
    <?php if ($aiSummary): ?>
    <div class="section">
        <?= sectionHeader('AI Intelligence Summary') ?>
        <div class="ai-summary"><?= e($aiSummary) ?></div>
    </div>
    <?php endif; ?>

    <!-- ===== Breaches ===== -->
    <?php
    $breachData = $sources['hibp']['data'] ?? $sources['breaches']['data'] ?? [];
    $breachItems = $breachData['items'] ?? $breachData;
    if (!empty($breachItems) && is_array($breachItems)):
    ?>
    <div class="section">
        <?= sectionHeader('Data Breaches (' . count($breachItems) . ')') ?>
        <?php foreach ($breachItems as $b): ?>
            <div class="breach-item">
                <div class="breach-name"><?= e($b['title'] ?? $b['name'] ?? $b['Name'] ?? 'Unknown') ?></div>
                <div class="breach-meta">
                    Breached: <?= e($b['breach_date'] ?? $b['BreachDate'] ?? 'Unknown') ?>
                    &middot; <?= number_format((int)($b['pwn_count'] ?? $b['PwnCount'] ?? 0)) ?> accounts
                </div>
                <?php if (!empty($b['data_classes'] ?? $b['DataClasses'] ?? [])): ?>
                    <div class="breach-classes">
                        <?php foreach (($b['data_classes'] ?? $b['DataClasses'] ?? []) as $cls): ?>
                            <?php
                            $isDanger = in_array($cls, ['Passwords', 'Password hints', 'Credit cards']);
                            ?>
                            <span class="tag <?= $isDanger ? 'tag-danger' : 'tag-info' ?>"><?= e($cls) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ===== GitHub ===== -->
    <?php
    $ghData = $sources['github']['data'] ?? [];
    if (!empty($ghData)):
    ?>
    <div class="section">
        <?= sectionHeader('GitHub Profile') ?>
        <?= dataRow('Public Repos', (string)($ghData['public_repos'] ?? '')) ?>
        <?= dataRow('Followers', (string)($ghData['followers'] ?? '')) ?>
        <?= dataRow('Following', (string)($ghData['following'] ?? '')) ?>
        <?= dataRow('Company', $ghData['company'] ?? '') ?>
        <?= dataRow('Member Since', $ghData['created_at'] ?? '') ?>
        <?php if (!empty($ghData['repos'])): ?>
            <div style="margin-top:10px;font-weight:600;font-size:10pt;color:#475569;">Top Repositories</div>
            <?php foreach (array_slice($ghData['repos'], 0, 5) as $repo): ?>
                <?= dataRow($repo['name'] ?? '', ($repo['language'] ?? '') . ' | ' . ($repo['stars'] ?? 0) . ' stars') ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ===== WHOIS ===== -->
    <?php
    $whoisData = $sources['whois']['data'] ?? [];
    if (!empty($whoisData)):
    ?>
    <div class="section">
        <?= sectionHeader('WHOIS Registration') ?>
        <?= dataRow('Domain', $whoisData['domain'] ?? '') ?>
        <?= dataRow('Registrar', $whoisData['registrar'] ?? '') ?>
        <?= dataRow('Registrant', $whoisData['registrant_org'] ?? '') ?>
        <?= dataRow('Country', $whoisData['registrant_country'] ?? '') ?>
        <?= dataRow('Created', $whoisData['created_date'] ?? '') ?>
        <?= dataRow('Expires', $whoisData['expiry_date'] ?? '') ?>
        <?= dataRow('Domain Age', $whoisData['domain_age'] ?? '') ?>
        <?= dataRow('DNSSEC', $whoisData['dnssec'] ?? '') ?>
        <?php if (!empty($whoisData['name_servers'])): ?>
            <?= dataRow('Name Servers', implode(', ', $whoisData['name_servers'])) ?>
        <?php endif; ?>
        <?php if (!empty($whoisData['is_privacy_protected'])): ?>
            <div style="margin-top:6px"><span class="tag tag-warning">Privacy Protected</span></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ===== DNS ===== -->
    <?php
    $dnsData = $sources['dns']['data'] ?? [];
    if (!empty($dnsData)):
    ?>
    <div class="section">
        <?= sectionHeader('DNS Intelligence') ?>
        <?= dataRow('Mail Provider', $dnsData['mail_provider'] ?? '') ?>
        <?php if (!empty($dnsData['hosting'])): ?>
            <?= dataRow('Infrastructure', implode(', ', $dnsData['hosting'])) ?>
        <?php endif; ?>
        <?php if (!empty($dnsData['mx_records'])): ?>
            <div style="margin-top:8px;font-weight:600;font-size:9pt;color:#475569;">MX Records</div>
            <?php foreach ($dnsData['mx_records'] as $mx): ?>
                <?= dataRow((string)($mx['priority'] ?? ''), $mx['host'] ?? '') ?>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($dnsData['a_records'])): ?>
            <?= dataRow('A Records', implode(', ', $dnsData['a_records'])) ?>
        <?php endif; ?>
        <?php if (!empty($dnsData['email_security'])): ?>
            <?php $sec = $dnsData['email_security']; ?>
            <div style="margin-top:8px;font-weight:600;font-size:9pt;color:#475569;">Email Security (Score: <?= (int)($sec['score'] ?? 0) ?>/100)</div>
            <div style="margin-top:4px;">
                <span class="tag <?= !empty($sec['spf']) ? 'tag-safe' : 'tag-danger' ?>"><?= !empty($sec['spf']) ? 'SPF' : 'No SPF' ?></span>
                <span class="tag <?= !empty($sec['dmarc']) ? 'tag-safe' : 'tag-danger' ?>"><?= !empty($sec['dmarc']) ? 'DMARC' : 'No DMARC' ?></span>
                <span class="tag <?= !empty($sec['has_dkim']) ? 'tag-safe' : 'tag-warning' ?>"><?= !empty($sec['has_dkim']) ? 'DKIM' : 'DKIM Unknown' ?></span>
            </div>
            <?php if (!empty($sec['issues'])): ?>
                <?php foreach ($sec['issues'] as $issue): ?>
                    <div style="font-size:8.5pt;color:#64748b;margin-top:2px;">&bull; <?= e($issue) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ===== SSL Certificate ===== -->
    <?php
    $sslData = $sources['ssl']['data'] ?? [];
    if (!empty($sslData)):
    ?>
    <div class="section">
        <?= sectionHeader('SSL Certificate') ?>
        <?php if (!empty($sslData['is_expired'])): ?>
            <div style="margin-bottom:6px"><span class="tag tag-danger">EXPIRED</span></div>
        <?php elseif (!empty($sslData['is_expiring_soon'])): ?>
            <div style="margin-bottom:6px"><span class="tag tag-warning">Expiring Soon</span></div>
        <?php else: ?>
            <div style="margin-bottom:6px"><span class="tag tag-safe">Valid</span></div>
        <?php endif; ?>
        <?= dataRow('Common Name', $sslData['subject_cn'] ?? '') ?>
        <?= dataRow('Certificate Authority', $sslData['certificate_authority'] ?? '') ?>
        <?= dataRow('Type', $sslData['cert_type'] ?? '') ?>
        <?= dataRow('Signature', $sslData['signature_algorithm'] ?? '') ?>
        <?php if (!empty($sslData['key_type'])): ?>
            <?= dataRow('Key', ($sslData['key_type'] ?? '') . ' ' . ($sslData['key_bits'] ?? '') . '-bit') ?>
        <?php endif; ?>
        <?= dataRow('Valid From', $sslData['valid_from'] ?? '') ?>
        <?= dataRow('Valid To', $sslData['valid_to'] ?? '') ?>
        <?php if (!empty($sslData['days_remaining'])): ?>
            <?= dataRow('Days Remaining', (string)$sslData['days_remaining']) ?>
        <?php endif; ?>
        <?php if (!empty($sslData['san_domains'])): ?>
            <?= dataRow('SAN Domains (' . ($sslData['san_count'] ?? count($sslData['san_domains'])) . ')', implode(', ', array_slice($sslData['san_domains'], 0, 10))) ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ===== VirusTotal ===== -->
    <?php
    $vtData = $sources['virustotal']['data'] ?? [];
    if (!empty($vtData)):
    ?>
    <div class="section">
        <?= sectionHeader('Threat Intelligence (VirusTotal)') ?>
        <?php
        $malicious   = (int)($vtData['malicious_count'] ?? 0);
        $suspicious  = (int)($vtData['suspicious_count'] ?? 0);
        $harmless    = (int)($vtData['harmless_count'] ?? 0);
        $undetected  = (int)($vtData['undetected_count'] ?? 0);
        $totalEng    = (int)($vtData['total_engines'] ?? ($malicious + $suspicious + $harmless + $undetected));
        ?>
        <div style="margin-bottom:8px;">
            <?php if ($malicious > 0): ?>
                <span class="tag tag-danger"><?= $malicious ?> malicious</span>
            <?php endif; ?>
            <?php if ($suspicious > 0): ?>
                <span class="tag tag-warning"><?= $suspicious ?> suspicious</span>
            <?php endif; ?>
            <span class="tag tag-safe"><?= $harmless + $undetected ?> clean</span>
            <span class="tag tag-muted"><?= $totalEng ?> engines scanned</span>
        </div>
        <?= dataRow('Reputation Score', (string)($vtData['reputation'] ?? '')) ?>
        <?= dataRow('Domain', $vtData['domain'] ?? '') ?>
        <?= dataRow('IP', $vtData['ip'] ?? '') ?>
        <?= dataRow('Country', $vtData['country'] ?? '') ?>
        <?= dataRow('AS Owner', $vtData['as_owner'] ?? '') ?>
        <?= dataRow('Network', $vtData['network'] ?? '') ?>
        <?= dataRow('Registrar', $vtData['registrar'] ?? '') ?>
        <?php if (!empty($vtData['categories'])): ?>
            <div style="margin-top:6px;">
                <?php foreach (array_unique(array_values($vtData['categories'])) as $cat): ?>
                    <span class="tag tag-muted"><?= e($cat) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ===== IP Intelligence ===== -->
    <?php
    $ipData = $sources['ipinfo']['data'] ?? $sources['ip']['data'] ?? [];
    if (!empty($ipData)):
    ?>
    <div class="section">
        <?= sectionHeader('IP Intelligence') ?>
        <?= dataRow('IP Address', $ipData['ip'] ?? '') ?>
        <?= dataRow('Hostname', $ipData['hostname'] ?? '') ?>
        <?php if (!empty($ipData['city'])): ?>
            <?= dataRow('Location', ($ipData['city'] ?? '') . ', ' . ($ipData['region'] ?? '') . ', ' . ($ipData['country'] ?? '')) ?>
        <?php endif; ?>
        <?= dataRow('Organization', $ipData['org'] ?? '') ?>
        <?= dataRow('Timezone', $ipData['timezone'] ?? '') ?>
        <?= dataRow('Postal Code', $ipData['postal'] ?? '') ?>
        <div style="margin-top:6px;">
            <span class="tag <?= !empty($ipData['is_vpn']) ? 'tag-danger' : 'tag-safe' ?>"><?= !empty($ipData['is_vpn']) ? 'VPN Detected' : 'No VPN' ?></span>
            <span class="tag <?= !empty($ipData['is_proxy']) ? 'tag-danger' : 'tag-safe' ?>"><?= !empty($ipData['is_proxy']) ? 'Proxy Detected' : 'No Proxy' ?></span>
            <span class="tag <?= !empty($ipData['is_tor']) ? 'tag-danger' : 'tag-safe' ?>"><?= !empty($ipData['is_tor']) ? 'Tor Exit Node' : 'No Tor' ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== Username Profiles ===== -->
    <?php
    $usernameData = $sources['username_osint']['data'] ?? $sources['username']['data'] ?? [];
    $usernameProfiles = $usernameData['profiles'] ?? [];
    if (!empty($usernameProfiles)):
        $found   = array_filter($usernameProfiles, fn($p) => !empty($p['exists']));
        $notFound = array_filter($usernameProfiles, fn($p) => empty($p['exists']));
    ?>
    <div class="section">
        <?= sectionHeader('Username Discovery (' . count($found) . '/' . count($usernameProfiles) . ' platforms)') ?>
        <?php if (!empty($found)): ?>
            <div style="margin-bottom:8px;">
                <?php foreach ($found as $p): ?>
                    <span class="tag tag-safe"><?= e($p['platform'] ?? '') ?></span>
                <?php endforeach; ?>
            </div>
            <?php foreach ($found as $p): ?>
                <?= dataRow($p['platform'] ?? '', $p['url'] ?? '') ?>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($notFound)): ?>
            <div style="margin-top:8px;font-size:8.5pt;color:#94a3b8;">
                Not found on: <?= e(implode(', ', array_map(fn($p) => $p['platform'] ?? '', $notFound))) ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ===== Google / Web Mentions ===== -->
    <?php
    $googleData = $sources['google']['data'] ?? [];
    $googleResults = $googleData['results'] ?? [];
    if (!empty($googleResults)):
    ?>
    <div class="section">
        <?= sectionHeader('Web Mentions') ?>
        <?php foreach (array_slice($googleResults, 0, 8) as $r): ?>
            <div style="padding:6px 0;border-bottom:1px solid #f1f5f9;">
                <div style="font-weight:600;font-size:10pt;color:#0ea5e9;"><?= e($r['title'] ?? '') ?></div>
                <div style="font-size:8pt;color:#22c55e;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($r['display_url'] ?? $r['url'] ?? '') ?></div>
                <?php if (!empty($r['snippet'])): ?>
                    <div style="font-size:9pt;color:#475569;margin-top:2px;"><?= e($r['snippet']) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ===== Risk Analysis ===== -->
    <?php
    // Try to pull risk factors from the profile data's source or the intelligence report
    $riskFactors = [];
    // Check each source for risk data that was stored
    foreach ($sources as $srcName => $srcInfo) {
        if (isset($srcInfo['data']['risk']['factors'])) {
            $riskFactors = $srcInfo['data']['risk']['factors'];
            break;
        }
    }
    if (!empty($riskFactors)):
    ?>
    <div class="section">
        <?= sectionHeader('Risk Analysis') ?>
        <div style="margin-bottom:10px;">
            <div style="height:8px;background:#f1f5f9;border-radius:4px;overflow:hidden;">
                <div style="width:<?= min($riskScore, 100) ?>%;height:100%;background:<?= riskColor($riskScore) ?>;border-radius:4px;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:8.5pt;color:#64748b;margin-top:4px;">
                <span><?= $riskScore <= 20 ? 'LOW' : ($riskScore <= 50 ? 'MODERATE' : ($riskScore <= 75 ? 'HIGH' : 'CRITICAL')) ?></span>
                <span><?= $riskScore ?>/100</span>
            </div>
        </div>
        <?php foreach ($riskFactors as $factor): ?>
            <?php
            $fl = strtolower($factor);
            $cls = 'tag-info';
            if (strpos($fl, 'password') !== false || strpos($fl, 'sensitive') !== false || strpos($fl, 'tor') !== false || strpos($fl, 'expired') !== false) {
                $cls = 'tag-danger';
            } elseif (strpos($fl, 'breach') !== false || strpos($fl, 'vpn') !== false || strpos($fl, 'proxy') !== false || strpos($fl, 'spoof') !== false) {
                $cls = 'tag-warning';
            }
            ?>
            <div style="padding:4px 0;font-size:9pt;">
                <span class="tag <?= $cls ?>"><?= e($factor) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ===== Footer ===== -->
    <div class="report-footer">
        <p>Generated by <strong>Vignette</strong> &mdash; AI-Powered Digital Intelligence Platform</p>
        <p>Report exported: <?= e($now) ?> | Search ID: #<?= $searchId ?></p>
        <p style="margin-top:6px;">All data sourced from publicly available information. Use responsibly.</p>
    </div>

</div>

</body>
</html>
