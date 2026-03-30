<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vignette — AI-Powered Digital Intelligence</title>
    <link rel="stylesheet" href="/vignette/frontend/css/style.css">
</head>
<body>
    <div class="app">
        <!-- Header -->
        <header class="header">
            <div class="header-brand">
                <div class="logo">
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                        <circle cx="16" cy="16" r="14" stroke="#00d4ff" stroke-width="2"/>
                        <circle cx="16" cy="16" r="8" stroke="#00d4ff" stroke-width="1.5" stroke-dasharray="3 3"/>
                        <circle cx="16" cy="16" r="3" fill="#00d4ff"/>
                    </svg>
                </div>
                <div>
                    <h1 class="brand-name">VIGNETTE</h1>
                    <p class="brand-tagline">AI-Powered Digital Intelligence</p>
                </div>
            </div>
            <nav class="header-nav">
                <a href="/vignette/" class="nav-link active">Search</a>
                <a href="/vignette/dashboard.php" class="nav-link">Dashboard</a>
                <button class="theme-toggle" id="themeToggle" title="Toggle theme" aria-label="Toggle dark/light theme">&#9790;</button>
            </nav>
        </header>

        <!-- Search Section -->
        <main class="search-section">
            <div class="search-hero">
                <h2 class="search-title">Intelligence Search</h2>
                <p class="search-subtitle">Enter a name, email, username, IP address, or domain to begin investigation</p>
            </div>

            <form id="searchForm" class="search-form" autocomplete="off">
                <div class="search-type-selector">
                    <button type="button" class="type-btn active" data-type="email">Email</button>
                    <button type="button" class="type-btn" data-type="username">Username</button>
                    <button type="button" class="type-btn" data-type="name">Name</button>
                    <button type="button" class="type-btn" data-type="ip">IP Address</button>
                    <button type="button" class="type-btn" data-type="domain">Domain</button>
                    <button type="button" class="type-btn" data-type="phone">Phone</button>
                </div>

                <div class="search-mode-toggle">
                    <button type="button" class="mode-btn active" data-mode="single" id="modeSingle">Single</button>
                    <button type="button" class="mode-btn" data-mode="bulk" id="modeBulk">Bulk</button>
                </div>

                <div class="search-input-group" id="singleInput">
                    <select id="phoneCountryCode" class="phone-cc-select hidden">
                        <option value="+91">+91 IN</option>
                        <option value="+1">+1 US/CA</option>
                        <option value="+44">+44 UK</option>
                        <option value="+61">+61 AU</option>
                        <option value="+86">+86 CN</option>
                        <option value="+81">+81 JP</option>
                        <option value="+49">+49 DE</option>
                        <option value="+33">+33 FR</option>
                        <option value="+39">+39 IT</option>
                        <option value="+34">+34 ES</option>
                        <option value="+55">+55 BR</option>
                        <option value="+52">+52 MX</option>
                        <option value="+7">+7 RU</option>
                        <option value="+82">+82 KR</option>
                        <option value="+971">+971 AE</option>
                        <option value="+966">+966 SA</option>
                        <option value="+65">+65 SG</option>
                        <option value="+60">+60 MY</option>
                        <option value="+62">+62 ID</option>
                        <option value="+63">+63 PH</option>
                        <option value="+66">+66 TH</option>
                        <option value="+84">+84 VN</option>
                        <option value="+27">+27 ZA</option>
                        <option value="+234">+234 NG</option>
                        <option value="+254">+254 KE</option>
                        <option value="+20">+20 EG</option>
                        <option value="+90">+90 TR</option>
                        <option value="+972">+972 IL</option>
                        <option value="+64">+64 NZ</option>
                        <option value="+56">+56 CL</option>
                        <option value="+57">+57 CO</option>
                        <option value="+54">+54 AR</option>
                        <option value="+48">+48 PL</option>
                        <option value="+46">+46 SE</option>
                        <option value="+47">+47 NO</option>
                        <option value="+45">+45 DK</option>
                        <option value="+41">+41 CH</option>
                        <option value="+31">+31 NL</option>
                        <option value="+32">+32 BE</option>
                        <option value="+353">+353 IE</option>
                        <option value="+351">+351 PT</option>
                        <option value="+43">+43 AT</option>
                        <option value="+30">+30 GR</option>
                        <option value="+380">+380 UA</option>
                        <option value="+40">+40 RO</option>
                    </select>
                    <input
                        type="text"
                        id="searchInput"
                        class="search-input"
                        placeholder="Enter email address..."
                        required
                    >
                    <button type="submit" class="search-btn" id="searchBtn">
                        <span class="btn-text">Investigate</span>
                        <span class="btn-loader hidden">
                            <span class="spinner"></span>
                            Scanning...
                        </span>
                    </button>
                </div>

                <div class="search-input-group hidden" id="bulkInput">
                    <textarea
                        id="bulkSearchInput"
                        class="search-input bulk-textarea"
                        placeholder="Enter one query per line (max 10)&#10;e.g.&#10;john@example.com&#10;jane@example.com"
                        rows="5"
                    ></textarea>
                    <button type="submit" class="search-btn" id="bulkSearchBtn">
                        <span class="btn-text">Investigate All</span>
                        <span class="btn-loader hidden">
                            <span class="spinner"></span>
                            <span id="bulkProgress">0/0</span>
                        </span>
                    </button>
                </div>
            </form>

            <!-- Results Container -->
            <div id="results" class="results-container hidden">
                <!-- Profile Card -->
                <div id="profileCard" class="card profile-card hidden">
                    <div class="profile-header">
                        <img id="profileAvatar" class="profile-avatar" alt="Avatar">
                        <div class="profile-info">
                            <h3 id="profileName" class="profile-name"></h3>
                            <p id="profileBio" class="profile-bio"></p>
                            <p id="profileCompany" class="profile-company"></p>
                            <p id="profileLocation" class="profile-location"></p>
                        </div>
                        <div id="riskBadge" class="risk-badge">
                            <span class="risk-score">0</span>
                            <span class="risk-label">Risk</span>
                        </div>
                    </div>
                    <div id="profileLinks" class="profile-links"></div>
                </div>

                <!-- Summary Stats Bar -->
                <div id="summaryBar" class="summary-bar hidden"></div>

                <!-- AI Intelligence Summary -->
                <div id="aiSummaryCard" class="card ai-summary-card hidden">
                    <div class="card-header">
                        <h3 class="card-title">AI Intelligence Briefing</h3>
                        <span class="card-badge">Gemini</span>
                    </div>
                    <div id="aiSummaryContent" class="card-body ai-summary-content"></div>
                </div>

                <!-- Related Intelligence -->
                <div id="relationshipsCard" class="card hidden">
                    <div class="card-header">
                        <h3 class="card-title">Related Intelligence</h3>
                        <span id="relationshipsBadge" class="card-badge"></span>
                    </div>
                    <div id="relationshipsData" class="card-body"></div>
                </div>

                <!-- Timeline -->
                <div id="timelineCard" class="card hidden">
                    <div class="card-header">
                        <h3 class="card-title">Intelligence Timeline</h3>
                    </div>
                    <div id="timelineData" class="card-body"></div>
                </div>

                <!-- Source Cards Grid -->
                <div class="results-grid">
                    <!-- Breach Results -->
                    <div id="breachCard" class="card hidden">
                        <div class="card-header">
                            <h3 class="card-title">Data Breaches</h3>
                            <span id="breachCount" class="card-badge">0</span>
                        </div>
                        <div id="breachList" class="card-body"></div>
                    </div>

                    <!-- GitHub Results -->
                    <div id="githubCard" class="card hidden">
                        <div class="card-header">
                            <h3 class="card-title">GitHub Profile</h3>
                        </div>
                        <div id="githubData" class="card-body"></div>
                    </div>

                    <!-- WHOIS Results -->
                    <div id="whoisCard" class="card hidden">
                        <div class="card-header">
                            <h3 class="card-title">WHOIS Registration</h3>
                            <span id="whoisPrivacy" class="card-badge hidden">Privacy Protected</span>
                        </div>
                        <div id="whoisData" class="card-body"></div>
                    </div>

                    <!-- DNS Records -->
                    <div id="dnsCard" class="card hidden">
                        <div class="card-header">
                            <h3 class="card-title">DNS Intelligence</h3>
                        </div>
                        <div id="dnsData" class="card-body"></div>
                    </div>

                    <!-- SSL Certificate -->
                    <div id="sslCard" class="card hidden">
                        <div class="card-header">
                            <h3 class="card-title">SSL Certificate</h3>
                            <span id="sslBadge" class="card-badge hidden"></span>
                        </div>
                        <div id="sslData" class="card-body"></div>
                    </div>

                    <!-- VirusTotal Results -->
                    <div id="vtCard" class="card hidden">
                        <div class="card-header">
                            <h3 class="card-title">Threat Intelligence</h3>
                            <span id="vtBadge" class="card-badge hidden"></span>
                        </div>
                        <div id="vtData" class="card-body"></div>
                    </div>

                    <!-- Username OSINT -->
                    <div id="usernameCard" class="card hidden">
                        <div class="card-header">
                            <h3 class="card-title">Username Discovery</h3>
                            <span id="usernameBadge" class="card-badge"></span>
                        </div>
                        <div id="usernameData" class="card-body"></div>
                    </div>

                    <!-- Phone Intelligence -->
                    <div id="phoneCard" class="card hidden">
                        <div class="card-header">
                            <h3 class="card-title">Phone Intelligence</h3>
                        </div>
                        <div id="phoneData" class="card-body"></div>
                    </div>

                    <!-- Google Search Results -->
                    <div id="googleCard" class="card hidden">
                        <div class="card-header">
                            <h3 class="card-title">Web Mentions</h3>
                            <span id="googleBadge" class="card-badge"></span>
                        </div>
                        <div id="googleData" class="card-body"></div>
                    </div>

                    <!-- IP Info Results -->
                    <div id="ipCard" class="card hidden">
                        <div class="card-header">
                            <h3 class="card-title">IP Intelligence</h3>
                        </div>
                        <div id="ipData" class="card-body"></div>
                    </div>

                    <!-- Risk Analysis -->
                    <div id="riskCard" class="card hidden">
                        <div class="card-header">
                            <h3 class="card-title">Risk Analysis</h3>
                        </div>
                        <div id="riskFactors" class="card-body"></div>
                    </div>
                </div>

                <!-- Action Bar: Export + Save + Watch -->
                <div id="exportBar" class="action-bar hidden">
                    <button id="saveBtn" class="export-btn" title="Save this profile for later">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="vertical-align:middle;margin-right:6px;">
                            <path d="M2 2a1 1 0 011-1h7l4 4v9a1 1 0 01-1 1H3a1 1 0 01-1-1V2z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            <path d="M5 1v4h5V1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            <path d="M5 10h6M5 12.5h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        Save
                    </button>
                    <button id="watchBtn" class="export-btn" title="Add to watchlist for monitoring">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="vertical-align:middle;margin-right:6px;">
                            <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            <circle cx="8" cy="8" r="2" fill="currentColor"/>
                        </svg>
                        Watch
                    </button>
                    <button id="exportBtn" class="export-btn" title="Export this report as a printable PDF">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="vertical-align:middle;margin-right:6px;">
                            <path d="M4 1h5l4 4v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            <path d="M9 1v4h4" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            <path d="M6 9h4M8 7v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        Export
                    </button>
                </div>

                <!-- Sources Meta -->
                <div id="metaBar" class="meta-bar hidden">
                    <span id="metaSources"></span>
                    <span id="metaTimestamp"></span>
                </div>
            </div>

            <!-- Bulk Results Container -->
            <div id="bulkResults" class="bulk-results-container hidden">
                <div id="bulkSummary" class="card bulk-summary-card">
                    <div class="card-header">
                        <h3 class="card-title">Bulk Search Results</h3>
                        <span id="bulkBadge" class="card-badge"></span>
                    </div>
                    <div id="bulkSummaryStats" class="summary-bar"></div>
                </div>
                <div id="bulkResultsList" class="bulk-results-list"></div>
            </div>

            <!-- Loading Skeleton -->
            <div id="loadingSkeleton" class="skeleton hidden">
                <div class="skeleton-card" style="border-color: var(--accent);">
                    <div class="skeleton-row">
                        <div class="skeleton-circle"></div>
                        <div style="flex:1">
                            <div class="skeleton-line thick w-50"></div>
                            <div class="skeleton-line w-70"></div>
                            <div class="skeleton-line w-40"></div>
                        </div>
                        <div class="skeleton-circle" style="width:60px;height:60px"></div>
                    </div>
                </div>
                <div style="display:flex;gap:12px;margin-bottom:16px">
                    <div class="skeleton-card" style="flex:1;margin-bottom:0"><div class="skeleton-line thick"></div><div class="skeleton-line w-50"></div></div>
                    <div class="skeleton-card" style="flex:1;margin-bottom:0"><div class="skeleton-line thick"></div><div class="skeleton-line w-50"></div></div>
                    <div class="skeleton-card" style="flex:1;margin-bottom:0"><div class="skeleton-line thick"></div><div class="skeleton-line w-50"></div></div>
                </div>
                <div class="skeleton-card">
                    <div class="skeleton-line w-40" style="margin-bottom:14px"></div>
                    <div class="skeleton-line w-90"></div>
                    <div class="skeleton-line"></div>
                    <div class="skeleton-line w-70"></div>
                </div>
                <div class="skeleton-grid">
                    <div class="skeleton-card"><div class="skeleton-line w-50"></div><div class="skeleton-line"></div><div class="skeleton-line w-70"></div></div>
                    <div class="skeleton-card"><div class="skeleton-line w-50"></div><div class="skeleton-line"></div><div class="skeleton-line w-70"></div></div>
                </div>
            </div>

            <!-- Error Display -->
            <div id="errorDisplay" class="error-display hidden"></div>
        </main>

        <!-- Footer -->
        <footer class="footer">
            <p>Vignette &mdash; AI-Powered Digital Intelligence Platform</p>
            <p class="footer-note">All data sourced from publicly available information. Use responsibly.</p>
        </footer>
    </div>

    <!-- Toast -->
    <div id="toast" class="toast"></div>

    <script src="/vignette/frontend/js/theme.js"></script>
    <script src="/vignette/frontend/js/app.js"></script>
</body>
</html>
