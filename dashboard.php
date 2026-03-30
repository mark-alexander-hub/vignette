<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vignette — Dashboard</title>
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
                <a href="/vignette/" class="nav-link">Search</a>
                <a href="/vignette/dashboard.php" class="nav-link active">Dashboard</a>
                <button class="theme-toggle" id="themeToggle" title="Toggle theme" aria-label="Toggle dark/light theme">&#9790;</button>
            </nav>
        </header>

        <!-- Dashboard Content -->
        <main class="search-section">
            <div class="search-hero">
                <h2 class="search-title">Dashboard</h2>
                <p class="search-subtitle">Search history, saved profiles, watchlist, and analytics</p>
            </div>

            <!-- Tabs -->
            <div class="dashboard-tabs">
                <button class="dashboard-tab active" data-tab="history">History</button>
                <button class="dashboard-tab" data-tab="saved">Saved</button>
                <button class="dashboard-tab" data-tab="watchlist">Watchlist</button>
                <button class="dashboard-tab" data-tab="analytics">Analytics</button>
            </div>

            <!-- ===== HISTORY TAB ===== -->
            <div class="tab-content active" id="tab-history">
                <div class="filter-toolbar">
                    <input type="text" id="historySearch" placeholder="Search queries...">
                    <select id="filterType">
                        <option value="">All Types</option>
                        <option value="email">Email</option>
                        <option value="username">Username</option>
                        <option value="name">Name</option>
                        <option value="ip">IP</option>
                        <option value="domain">Domain</option>
                        <option value="phone">Phone</option>
                    </select>
                    <select id="filterRisk">
                        <option value="">All Risk</option>
                        <option value="low">Low (0-20)</option>
                        <option value="moderate">Moderate (21-50)</option>
                        <option value="high">High (51-75)</option>
                        <option value="critical">Critical (76-100)</option>
                    </select>
                    <select id="historySort">
                        <option value="date_desc">Newest</option>
                        <option value="date_asc">Oldest</option>
                        <option value="risk_desc">Risk High-Low</option>
                        <option value="risk_asc">Risk Low-High</option>
                    </select>
                </div>
                <div id="historyList" class="history-list">
                    <p style="text-align:center;color:var(--text-muted)">Loading...</p>
                </div>
                <div id="historyPagination" class="pagination hidden"></div>
            </div>

            <!-- ===== SAVED PROFILES TAB ===== -->
            <div class="tab-content" id="tab-saved">
                <div id="savedTagFilter" class="tag-filter-row"></div>
                <div id="savedList" class="history-list">
                    <p style="text-align:center;color:var(--text-muted)">Loading...</p>
                </div>
            </div>

            <!-- ===== WATCHLIST TAB ===== -->
            <div class="tab-content" id="tab-watchlist">
                <div id="watchlistList" class="history-list">
                    <p style="text-align:center;color:var(--text-muted)">Loading...</p>
                </div>
            </div>

            <!-- ===== ANALYTICS TAB ===== -->
            <div class="tab-content" id="tab-analytics">
                <div id="analyticsContent" class="analytics-container">
                    <p style="text-align:center;color:var(--text-muted)">Loading analytics...</p>
                </div>
            </div>
        </main>

        <footer class="footer">
            <p>Vignette &mdash; AI-Powered Digital Intelligence Platform</p>
            <p class="footer-note">All data sourced from publicly available information. Use responsibly.</p>
        </footer>
    </div>

    <!-- Toast -->
    <div id="toast" class="toast"></div>

    <script src="/vignette/frontend/js/theme.js"></script>
    <script>
    (function() {
        const API = '/vignette/api/index.php';

        // ===== Utils =====
        function esc(str) {
            const d = document.createElement('div');
            d.textContent = str || '';
            return d.innerHTML;
        }

        // Escape for use inside HTML attribute quotes (prevents XSS in attributes)
        function escAttr(str) {
            return (str || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        function toast(msg, type) {
            const t = document.getElementById('toast');
            if (!t) return;
            t.textContent = msg;
            t.className = 'toast show' + (type ? ' ' + type : '');
            setTimeout(() => t.className = 'toast', 3000);
        }

        function riskLevel(score) {
            const s = parseInt(score, 10) || 0;
            if (s <= 20) return 'low';
            if (s <= 50) return 'moderate';
            if (s <= 75) return 'high';
            return 'critical';
        }

        async function api(route, opts = {}) {
            const url = opts.params ? `${API}?route=${route}&${new URLSearchParams(opts.params)}` : `${API}?route=${route}`;
            const fetchOpts = {};
            if (opts.body) {
                fetchOpts.method = 'POST';
                fetchOpts.headers = { 'Content-Type': 'application/json' };
                fetchOpts.body = JSON.stringify(opts.body);
            }
            const res = await fetch(url, fetchOpts);
            if (!res.ok) {
                const text = await res.text();
                try { return JSON.parse(text); } catch(e) { return { error: 'Server error (' + res.status + ')' }; }
            }
            return res.json();
        }

        // ===== Event Delegation =====
        // All click handlers via data-action attributes — no inline onclick, no XSS
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            const action = btn.dataset.action;
            const id = parseInt(btn.dataset.id, 10);

            if (action === 'replay') {
                window.location.href = '/vignette/?replay=' + encodeURIComponent(id);
            } else if (action === 'history-page') {
                historyState.page = id;
                loadHistory();
            } else if (action === 'filter-tag') {
                savedTagFilter = btn.dataset.tag || '';
                loadSavedProfiles();
            } else if (action === 'edit-profile') {
                showEditModal(id);
            } else if (action === 'delete-profile') {
                if (!confirm('Remove this saved profile?')) return;
                api('delete-saved-profile', { body: { id } }).then(data => {
                    if (data.success) { toast('Profile removed', 'success'); loadSavedProfiles(); }
                    else toast(data.error || 'Failed', 'error');
                });
            } else if (action === 'toggle-watch') {
                api('watchlist-toggle', { body: { id } }).then(() => loadWatchlist());
            } else if (action === 'recheck-watch') {
                recheckWatch(id, btn);
            } else if (action === 'delete-watch') {
                if (!confirm('Remove from watchlist?')) return;
                api('watchlist-delete', { body: { id } }).then(data => {
                    if (data.success) { toast('Removed from watchlist', 'success'); loadWatchlist(); }
                    else toast(data.error || 'Failed', 'error');
                });
            }
        });

        document.addEventListener('change', function(e) {
            const el = e.target.closest('[data-action="toggle-watch-check"]');
            if (el) {
                const id = parseInt(el.dataset.id, 10);
                api('watchlist-toggle', { body: { id } }).then(() => loadWatchlist());
            }
        });

        // ===== Tabs =====
        const tabs = document.querySelectorAll('.dashboard-tab');
        const loaded = {};

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById('tab-' + tab.dataset.tab).classList.add('active');

                if (!loaded[tab.dataset.tab]) {
                    loaded[tab.dataset.tab] = true;
                    if (tab.dataset.tab === 'saved') loadSavedProfiles();
                    if (tab.dataset.tab === 'watchlist') loadWatchlist();
                    if (tab.dataset.tab === 'analytics') loadAnalytics();
                }
            });
        });

        // ===== HISTORY =====
        let historyState = { page: 1, type: '', risk: '', q: '', sort: 'date_desc' };
        let searchTimeout;

        document.getElementById('historySearch').addEventListener('input', e => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => { historyState.q = e.target.value; historyState.page = 1; loadHistory(); }, 300);
        });
        document.getElementById('filterType').addEventListener('change', e => { historyState.type = e.target.value; historyState.page = 1; loadHistory(); });
        document.getElementById('filterRisk').addEventListener('change', e => { historyState.risk = e.target.value; historyState.page = 1; loadHistory(); });
        document.getElementById('historySort').addEventListener('change', e => { historyState.sort = e.target.value; historyState.page = 1; loadHistory(); });

        async function loadHistory() {
            const list = document.getElementById('historyList');
            const params = { per_page: 20, page: historyState.page, sort: historyState.sort };
            if (historyState.type) params.type = historyState.type;
            if (historyState.risk) params.risk = historyState.risk;
            if (historyState.q) params.q = historyState.q;

            try {
                const data = await api('history', { params });

                if (!data.searches || data.searches.length === 0) {
                    list.innerHTML = '<div class="empty-state"><p>No searches found.</p><p style="margin-top:8px"><a href="/vignette/" style="color:var(--accent)">Run a search</a></p></div>';
                    document.getElementById('historyPagination').classList.add('hidden');
                    return;
                }

                list.innerHTML = data.searches.map(s => {
                    const risk = parseInt(s.risk_score, 10) || 0;
                    const level = riskLevel(risk);
                    const date = new Date(s.created_at).toLocaleString();
                    return `<div class="history-item" data-action="replay" data-id="${s.id}">
                        ${s.avatar_url ? `<img class="history-avatar" src="${escAttr(s.avatar_url)}" alt="">` : '<div class="history-avatar"></div>'}
                        <div class="history-info">
                            <div class="history-query">${esc(s.display_name || s.query_value)}</div>
                            <div class="history-type">${esc(s.query_type)} &middot; <span class="history-date">${date}</span></div>
                        </div>
                        <span class="history-risk ${level}">${risk}</span>
                    </div>`;
                }).join('');

                // Pagination
                const pg = data.pagination;
                const pgEl = document.getElementById('historyPagination');
                if (pg && pg.total_pages > 1) {
                    pgEl.classList.remove('hidden');
                    pgEl.innerHTML = `
                        <button data-action="history-page" data-id="${pg.page - 1}" ${pg.page <= 1 ? 'disabled' : ''}>Prev</button>
                        <span class="pagination-info">Page ${pg.page} of ${pg.total_pages} (${pg.total} results)</span>
                        <button data-action="history-page" data-id="${pg.page + 1}" ${pg.page >= pg.total_pages ? 'disabled' : ''}>Next</button>
                    `;
                } else {
                    pgEl.classList.add('hidden');
                }
            } catch (e) {
                list.innerHTML = '<div class="empty-state"><p>Failed to load history</p></div>';
            }
        }

        // ===== SAVED PROFILES =====
        let savedTagFilter = '';
        let savedProfilesCache = []; // cache for edit lookups

        async function loadSavedProfiles() {
            const list = document.getElementById('savedList');
            const filterRow = document.getElementById('savedTagFilter');

            try {
                const params = {};
                if (savedTagFilter) params.tag = savedTagFilter;
                const data = await api('saved-profiles', { params });

                savedProfilesCache = data.profiles || [];

                // Tag filter row
                if (data.all_tags && data.all_tags.length > 0) {
                    filterRow.innerHTML = `<span class="tag-filter-label">Filter:</span>
                        <span class="tag-pill ${!savedTagFilter ? 'active' : ''}" data-action="filter-tag" data-tag="">All</span>
                        ${data.all_tags.map(t => `<span class="tag-pill ${savedTagFilter === t ? 'active' : ''}" data-action="filter-tag" data-tag="${escAttr(t)}">${esc(t)}</span>`).join('')}`;
                } else {
                    filterRow.innerHTML = '';
                }

                if (!data.profiles || data.profiles.length === 0) {
                    list.innerHTML = '<div class="empty-state"><p>No saved profiles yet.</p><p style="margin-top:8px;color:var(--text-muted)">Save a profile from search results to see it here.</p></div>';
                    return;
                }

                list.innerHTML = data.profiles.map(sp => {
                    const risk = parseInt(sp.risk_score, 10) || 0;
                    const level = riskLevel(risk);
                    const tags = sp.tags ? sp.tags.split(',').map(t => `<span class="tag-pill">${esc(t.trim())}</span>`).join('') : '';
                    return `<div class="saved-profile-card">
                        <div class="saved-profile-header">
                            <div class="saved-profile-info">
                                <div class="saved-profile-label">${esc(sp.label || sp.display_name || sp.query_value)}</div>
                                <div class="saved-profile-query">${esc(sp.query_type)}: ${esc(sp.query_value)}</div>
                                ${tags ? `<div class="tag-pills">${tags}</div>` : ''}
                            </div>
                            <span class="history-risk ${level}">${risk}</span>
                            <div class="saved-profile-actions">
                                <button data-action="edit-profile" data-id="${sp.id}">Edit</button>
                                <button data-action="delete-profile" data-id="${sp.id}">Delete</button>
                            </div>
                        </div>
                        ${sp.notes ? `<div class="saved-profile-notes">${esc(sp.notes)}</div>` : ''}
                    </div>`;
                }).join('');
            } catch (e) {
                list.innerHTML = '<div class="empty-state"><p>Failed to load saved profiles</p></div>';
            }
        }

        function showEditModal(id) {
            const sp = savedProfilesCache.find(p => p.id == id);
            if (!sp) return;

            const modal = document.createElement('div');
            modal.className = 'save-modal';
            const content = document.createElement('div');
            content.className = 'save-modal-content';
            content.innerHTML = '<h3>Edit Saved Profile</h3>';

            // Build form safely (no innerHTML with user data)
            const fields = [
                { label: 'Label', id: 'editLabel', type: 'input', value: sp.label || '' },
                { label: 'Notes', id: 'editNotes', type: 'textarea', value: sp.notes || '' },
                { label: 'Tags', id: 'editTags', type: 'input', value: sp.tags || '' },
            ];
            fields.forEach(f => {
                const lbl = document.createElement('label');
                lbl.textContent = f.label;
                content.appendChild(lbl);
                const el = document.createElement(f.type === 'textarea' ? 'textarea' : 'input');
                el.id = f.id;
                if (f.type === 'textarea') el.rows = 3;
                el.value = f.value;
                content.appendChild(el);
            });
            const hint = document.createElement('div');
            hint.className = 'hint';
            hint.textContent = 'Comma-separated, e.g. suspect, priority, reviewed';
            content.appendChild(hint);

            const actions = document.createElement('div');
            actions.className = 'save-modal-actions';
            const cancelBtn = document.createElement('button');
            cancelBtn.className = 'btn-secondary';
            cancelBtn.textContent = 'Cancel';
            cancelBtn.addEventListener('click', () => modal.remove());
            const saveBtn = document.createElement('button');
            saveBtn.className = 'btn-primary';
            saveBtn.textContent = 'Save';
            saveBtn.addEventListener('click', async () => {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';
                const data = await api('update-saved-profile', { body: {
                    id,
                    label: document.getElementById('editLabel').value,
                    notes: document.getElementById('editNotes').value,
                    tags: document.getElementById('editTags').value,
                }});
                modal.remove();
                if (data.success) { toast('Profile updated', 'success'); loadSavedProfiles(); }
                else toast(data.error || 'Failed', 'error');
            });
            actions.appendChild(cancelBtn);
            actions.appendChild(saveBtn);
            content.appendChild(actions);
            modal.appendChild(content);

            document.body.appendChild(modal);
            modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
            // ESC to close
            const escHandler = e => { if (e.key === 'Escape') { modal.remove(); document.removeEventListener('keydown', escHandler); } };
            document.addEventListener('keydown', escHandler);
        }

        // ===== WATCHLIST =====
        async function loadWatchlist() {
            const list = document.getElementById('watchlistList');
            try {
                const data = await api('watchlist');

                if (!data.items || data.items.length === 0) {
                    list.innerHTML = '<div class="empty-state"><p>Watchlist is empty.</p><p style="margin-top:8px;color:var(--text-muted)">Add items to watch from search results.</p></div>';
                    return;
                }

                list.innerHTML = data.items.map(w => {
                    const active = w.active == 1;
                    const lastChecked = w.last_checked ? new Date(w.last_checked).toLocaleString() : 'Never';
                    const risk = parseInt(w.last_risk_score, 10) || 0;
                    const level = riskLevel(risk);
                    return `<div class="watchlist-item ${active ? '' : 'inactive'}" id="watch-${w.id}">
                        <label class="toggle-switch">
                            <input type="checkbox" ${active ? 'checked' : ''} data-action="toggle-watch-check" data-id="${w.id}">
                            <span class="toggle-slider"></span>
                        </label>
                        <div class="watchlist-info">
                            <div class="watchlist-query">${esc(w.query_value)}</div>
                            <div class="watchlist-meta">${esc(w.query_type)} &middot; Last checked: ${lastChecked}</div>
                            <div id="recheck-result-${w.id}"></div>
                        </div>
                        <span class="history-risk ${level}">${risk}</span>
                        <div class="watchlist-actions">
                            <button class="recheck-btn" data-action="recheck-watch" data-id="${w.id}">Re-check</button>
                            <button class="delete-btn" data-action="delete-watch" data-id="${w.id}">Remove</button>
                        </div>
                    </div>`;
                }).join('');
            } catch (e) {
                list.innerHTML = '<div class="empty-state"><p>Failed to load watchlist</p></div>';
            }
        }

        async function recheckWatch(id, btn) {
            const origText = btn.textContent;
            btn.textContent = 'Checking...';
            btn.disabled = true;

            try {
                const data = await api('watchlist-recheck', { body: { id } });
                const resultEl = document.getElementById('recheck-result-' + id);

                if (data.success && resultEl) {
                    let changeText = '';
                    if (data.risk_change !== null && data.risk_change !== undefined) {
                        const cls = data.risk_change > 0 ? 'risk-up' : data.risk_change < 0 ? 'risk-down' : 'risk-same';
                        const sign = data.risk_change > 0 ? '+' : '';
                        changeText = `Risk: ${data.old_risk} &rarr; ${data.new_risk} (<span class="${cls}">${sign}${data.risk_change}</span>)`;
                    } else {
                        changeText = `Risk: ${data.new_risk} (first check)`;
                    }
                    if (data.summary_changed) changeText += ' &middot; Summary changed';
                    resultEl.innerHTML = `<div class="recheck-result">${changeText}</div>`;
                    toast('Re-check complete', 'success');
                } else {
                    toast(data.error || 'Re-check failed', 'error');
                }
            } catch (e) {
                toast('Re-check failed', 'error');
            }

            btn.textContent = origText;
            btn.disabled = false;
        }

        // ===== ANALYTICS =====
        async function loadAnalytics() {
            const container = document.getElementById('analyticsContent');
            try {
                const data = await api('analytics');
                if (!data.success) {
                    container.innerHTML = '<div class="empty-state"><p>Failed to load analytics</p></div>';
                    return;
                }
                renderAnalytics(data, container);
            } catch (e) {
                container.innerHTML = '<div class="empty-state"><p>Failed to load analytics</p></div>';
            }
        }

        function renderAnalytics(data, container) {
            const avgRiskLevel = riskLevel(data.avg_risk);

            // --- Stats Row ---
            let html = `<div class="analytics-stats">
                <div class="summary-stat"><div class="summary-stat-value accent">${data.total_searches}</div><div class="summary-stat-label">Total Searches</div></div>
                <div class="summary-stat"><div class="summary-stat-value ${avgRiskLevel === 'low' || avgRiskLevel === 'clean' ? 'success' : avgRiskLevel === 'moderate' ? 'warning' : 'danger'}">${data.avg_risk}/100</div><div class="summary-stat-label">Avg Risk</div></div>
                <div class="summary-stat"><div class="summary-stat-value accent">${data.total_saved}</div><div class="summary-stat-label">Saved Profiles</div></div>
                <div class="summary-stat"><div class="summary-stat-value accent">${data.total_watchlist}</div><div class="summary-stat-label">Active Watchlist</div></div>
            </div>`;

            // --- Charts Grid ---
            html += '<div class="analytics-grid">';

            // 1. Searches by Type (pie chart via conic-gradient)
            html += '<div class="analytics-card">';
            html += '<h4 class="analytics-card-title">Searches by Type</h4>';
            const typeColors = { email: '#00d4ff', username: '#22c55e', name: '#f59e0b', ip: '#ef4444', domain: '#8b5cf6', phone: '#ec4899' };
            const typeEntries = Object.entries(data.by_type || {});
            const typeTotal = typeEntries.reduce((s, [, c]) => s + c, 0) || 1;
            if (typeEntries.length > 0) {
                let gradParts = [];
                let cumPct = 0;
                typeEntries.forEach(([type, count]) => {
                    const pct = (count / typeTotal) * 100;
                    const color = typeColors[type] || '#64748b';
                    gradParts.push(`${color} ${cumPct}% ${cumPct + pct}%`);
                    cumPct += pct;
                });
                html += `<div style="display:flex;align-items:center;gap:20px">
                    <div style="width:120px;height:120px;border-radius:50%;background:conic-gradient(${gradParts.join(',')});flex-shrink:0"></div>
                    <div class="analytics-legend">
                        ${typeEntries.map(([type, count]) => `<div class="legend-item"><span class="legend-dot" style="background:${typeColors[type] || '#64748b'}"></span>${esc(type)} <span style="color:var(--text-muted)">${count}</span></div>`).join('')}
                    </div>
                </div>`;
            } else {
                html += '<div class="empty-state" style="padding:20px">No data yet</div>';
            }
            html += '</div>';

            // 2. Risk Distribution (horizontal stacked bar)
            html += '<div class="analytics-card">';
            html += '<h4 class="analytics-card-title">Risk Distribution</h4>';
            const riskColors = { clean: '#22c55e', low: '#22c55e', moderate: '#f59e0b', high: '#f97316', critical: '#ef4444' };
            const riskEntries = Object.entries(data.risk_distribution || {});
            const riskTotal = riskEntries.reduce((s, [, c]) => s + c, 0) || 1;
            if (riskEntries.length > 0) {
                html += '<div class="risk-bar-container">';
                riskEntries.forEach(([level, count]) => {
                    const pct = (count / riskTotal) * 100;
                    if (pct > 0) {
                        html += `<div class="risk-bar-segment" style="width:${pct}%;background:${riskColors[level] || '#64748b'}" title="${esc(level)}: ${count} (${Math.round(pct)}%)"></div>`;
                    }
                });
                html += '</div>';
                html += '<div class="analytics-legend" style="margin-top:10px">';
                riskEntries.forEach(([level, count]) => {
                    html += `<div class="legend-item"><span class="legend-dot" style="background:${riskColors[level] || '#64748b'}"></span>${esc(level)} <span style="color:var(--text-muted)">${count}</span></div>`;
                });
                html += '</div>';
            } else {
                html += '<div class="empty-state" style="padding:20px">No data yet</div>';
            }
            html += '</div>';

            // 3. Searches Over Time (bar chart)
            html += '<div class="analytics-card analytics-card-wide">';
            html += '<h4 class="analytics-card-title">Searches Over Time (Last 30 Days)</h4>';
            const dailyData = data.daily || [];
            if (dailyData.length > 0) {
                const maxCount = Math.max(...dailyData.map(d => d.count));
                html += '<div class="daily-chart">';
                dailyData.forEach(d => {
                    const pct = maxCount > 0 ? (d.count / maxCount) * 100 : 0;
                    const label = d.day.slice(5); // MM-DD
                    html += `<div class="daily-bar-row">
                        <span class="daily-bar-label">${esc(label)}</span>
                        <div class="daily-bar-track"><div class="daily-bar-fill" style="width:${pct}%"></div></div>
                        <span class="daily-bar-count">${d.count}</span>
                    </div>`;
                });
                html += '</div>';
            } else {
                html += '<div class="empty-state" style="padding:20px">No searches in the last 30 days</div>';
            }
            html += '</div>';

            // 4. Top Platforms (horizontal bar chart)
            html += '<div class="analytics-card analytics-card-wide">';
            html += '<h4 class="analytics-card-title">Top Platforms Found</h4>';
            const platforms = Object.entries(data.top_platforms || {});
            if (platforms.length > 0) {
                const maxPlat = platforms[0][1];
                html += '<div class="platform-chart">';
                platforms.forEach(([name, count]) => {
                    const pct = maxPlat > 0 ? (count / maxPlat) * 100 : 0;
                    html += `<div class="daily-bar-row">
                        <span class="daily-bar-label" style="width:90px">${esc(name)}</span>
                        <div class="daily-bar-track"><div class="daily-bar-fill" style="width:${pct}%;background:var(--accent)"></div></div>
                        <span class="daily-bar-count">${count}</span>
                    </div>`;
                });
                html += '</div>';
            } else {
                html += '<div class="empty-state" style="padding:20px">No platform data yet</div>';
            }
            html += '</div>';

            html += '</div>'; // close analytics-grid

            container.innerHTML = html;
        }

        // ===== Init =====
        loaded['history'] = true;
        loadHistory();
    })();
    </script>

    <style>
        .history-list { max-width: 700px; margin: 0 auto; }
        .history-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 8px;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .history-item:hover { border-color: var(--accent); }
        .history-avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: var(--bg-secondary);
            object-fit: cover;
        }
        .history-info { flex: 1; }
        .history-query { font-weight: 600; font-size: 0.95rem; }
        .history-type {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .history-date { font-size: 0.8rem; color: var(--text-muted); }
        .history-risk {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .history-risk.low { background: var(--success-dim); color: var(--success); }
        .history-risk.moderate { background: var(--warning-dim); color: var(--warning); }
        .history-risk.high { background: rgba(249,115,22,0.15); color: #f97316; }
        .history-risk.critical { background: var(--danger-dim); color: var(--danger); }
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }

        /* Analytics */
        .analytics-container { max-width: 800px; margin: 0 auto; }
        .analytics-stats {
            display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;
        }
        .analytics-stats .summary-stat {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 14px 18px; flex: 1; min-width: 120px; text-align: center;
        }
        .analytics-stats .summary-stat-value { font-size: 1.4rem; font-weight: 700; }
        .analytics-stats .summary-stat-value.accent { color: var(--accent); }
        .analytics-stats .summary-stat-value.success { color: var(--success); }
        .analytics-stats .summary-stat-value.warning { color: var(--warning); }
        .analytics-stats .summary-stat-value.danger { color: var(--danger); }
        .analytics-stats .summary-stat-label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }

        .analytics-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
        }
        .analytics-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius-lg); padding: 20px;
        }
        .analytics-card-wide { grid-column: 1 / -1; }
        .analytics-card-title {
            font-size: 0.9rem; font-weight: 600; color: var(--accent);
            margin-bottom: 14px; padding-bottom: 8px; border-bottom: 1px solid var(--border);
        }
        .analytics-legend { display: flex; flex-wrap: wrap; gap: 8px 16px; }
        .legend-item { display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: var(--text-primary); text-transform: capitalize; }
        .legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }

        .risk-bar-container {
            display: flex; height: 28px; border-radius: var(--radius); overflow: hidden; background: var(--bg-secondary);
        }
        .risk-bar-segment { transition: width 0.3s; min-width: 2px; }

        .daily-chart, .platform-chart { display: flex; flex-direction: column; gap: 6px; max-height: 350px; overflow-y: auto; }
        .daily-bar-row { display: flex; align-items: center; gap: 8px; }
        .daily-bar-label { font-size: 0.72rem; color: var(--text-muted); font-family: var(--font-mono); width: 50px; text-align: right; flex-shrink: 0; }
        .daily-bar-track { flex: 1; height: 18px; background: var(--bg-secondary); border-radius: 3px; overflow: hidden; }
        .daily-bar-fill { height: 100%; background: var(--accent); border-radius: 3px; transition: width 0.3s; min-width: 2px; }
        .daily-bar-count { font-size: 0.75rem; color: var(--text-secondary); width: 30px; text-align: right; font-weight: 600; }

        @media (max-width: 640px) {
            .analytics-grid { grid-template-columns: 1fr; }
            .analytics-stats { flex-direction: column; }
        }
    </style>
</body>
</html>
