/**
 * Vignette — Frontend Application
 * Handles search form, API calls, and result rendering.
 */

(function () {
    'use strict';

    // ============ State ============
    let currentType = 'email';
    let lastSearchId = null;

    const placeholders = {
        email: 'Enter email address...',
        username: 'Enter username or handle...',
        name: 'Enter full name...',
        ip: 'Enter IP address...',
        domain: 'Enter domain name...',
        phone: 'Enter phone number...'
    };

    // ============ DOM Elements ============
    const searchForm = document.getElementById('searchForm');
    const searchInput = document.getElementById('searchInput');
    const searchBtn = document.getElementById('searchBtn');
    const btnText = searchBtn.querySelector('.btn-text');
    const btnLoader = searchBtn.querySelector('.btn-loader');
    const resultsContainer = document.getElementById('results');
    const errorDisplay = document.getElementById('errorDisplay');
    const typeButtons = document.querySelectorAll('.type-btn');
    const exportBar = document.getElementById('exportBar');
    const exportBtn = document.getElementById('exportBtn');
    const loadingSkeleton = document.getElementById('loadingSkeleton');

    // ============ Type Selector ============
    const phoneCountryCode = document.getElementById('phoneCountryCode');

    typeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            typeButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentType = btn.dataset.type;
            searchInput.placeholder = placeholders[currentType] || 'Enter search query...';
            // Show/hide country code selector for phone type
            if (phoneCountryCode) {
                if (currentType === 'phone') {
                    phoneCountryCode.classList.remove('hidden');
                    searchInput.style.borderRadius = '0 var(--radius) var(--radius) 0';
                } else {
                    phoneCountryCode.classList.add('hidden');
                    searchInput.style.borderRadius = '';
                }
            }
            searchInput.focus();
        });
    });

    // ============ Toast ============
    function showToast(msg, type) {
        const t = document.getElementById('toast');
        if (!t) return;
        t.textContent = msg;
        t.className = 'toast show' + (type ? ' ' + type : '');
        setTimeout(() => t.className = 'toast', 3000);
    }

    // ============ Export Button ============
    if (exportBtn) {
        exportBtn.addEventListener('click', () => {
            if (lastSearchId) {
                window.open('/vignette/api/export.php?search_id=' + lastSearchId, '_blank');
            }
        });
    }

    // ============ Save Profile Button ============
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            if (!lastSearchId) return;
            const nameEl = document.getElementById('profileName');
            const displayName = (nameEl && nameEl.textContent) ? nameEl.textContent : searchInput.value;
            showSaveModal(lastSearchId, displayName);
        });
    }

    function showSaveModal(searchId, defaultLabel) {
        const modal = document.createElement('div');
        modal.className = 'save-modal';
        const content = document.createElement('div');
        content.className = 'save-modal-content';
        content.innerHTML = '<h3>Save Profile</h3>';

        // Build form with safe DOM methods
        const fields = [
            { label: 'Label', id: 'saveLabel', type: 'input', value: defaultLabel || '', placeholder: '' },
            { label: 'Notes', id: 'saveNotes', type: 'textarea', value: '', placeholder: 'Add notes about this profile...' },
            { label: 'Tags', id: 'saveTags', type: 'input', value: '', placeholder: 'e.g. suspect, priority, reviewed' },
        ];
        fields.forEach(f => {
            const lbl = document.createElement('label');
            lbl.textContent = f.label;
            content.appendChild(lbl);
            const el = document.createElement(f.type === 'textarea' ? 'textarea' : 'input');
            el.id = f.id;
            if (f.type === 'textarea') el.rows = 3;
            el.value = f.value;
            if (f.placeholder) el.placeholder = f.placeholder;
            content.appendChild(el);
        });
        const hint = document.createElement('div');
        hint.className = 'hint';
        hint.textContent = 'Comma-separated tags for easy filtering';
        content.appendChild(hint);

        const actions = document.createElement('div');
        actions.className = 'save-modal-actions';
        const cancelBtn = document.createElement('button');
        cancelBtn.className = 'btn-secondary';
        cancelBtn.textContent = 'Cancel';
        cancelBtn.addEventListener('click', () => modal.remove());
        const confirmBtn = document.createElement('button');
        confirmBtn.className = 'btn-primary';
        confirmBtn.textContent = 'Save';
        confirmBtn.addEventListener('click', async () => {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Saving...';
            try {
                const res = await fetch('/vignette/api/index.php?route=save-profile', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        search_id: searchId,
                        label: document.getElementById('saveLabel').value,
                        notes: document.getElementById('saveNotes').value,
                        tags: document.getElementById('saveTags').value,
                    })
                });
                const data = res.ok ? await res.json() : { error: 'Server error' };
                modal.remove();
                if (data.success) showToast('Profile saved!', 'success');
                else showToast(data.error || 'Failed to save', 'error');
            } catch (e) {
                modal.remove();
                showToast('Failed to save profile', 'error');
            }
        });
        actions.appendChild(cancelBtn);
        actions.appendChild(confirmBtn);
        content.appendChild(actions);
        modal.appendChild(content);

        document.body.appendChild(modal);
        modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
        const escHandler = e => { if (e.key === 'Escape') { modal.remove(); document.removeEventListener('keydown', escHandler); } };
        document.addEventListener('keydown', escHandler);
    }

    // ============ Watch Button ============
    const watchBtn = document.getElementById('watchBtn');
    if (watchBtn) {
        watchBtn.addEventListener('click', async () => {
            if (!lastSearchId) return;
            watchBtn.disabled = true;
            const origHTML = watchBtn.innerHTML;
            watchBtn.textContent = 'Adding...';
            try {
                const res = await fetch('/vignette/api/index.php?route=watchlist-add', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        query_value: searchInput.value.trim(),
                        query_type: currentType,
                    })
                });
                const data = res.ok ? await res.json() : { error: 'Server error' };
                if (data.success) showToast('Added to watchlist!', 'success');
                else showToast(data.error || 'Failed', 'error');
            } catch (e) {
                showToast('Failed to add to watchlist', 'error');
            }
            watchBtn.innerHTML = origHTML;
            watchBtn.disabled = false;
        });
    }

    // ============ Bulk Mode Toggle ============
    let bulkMode = false;
    const modeSingle = document.getElementById('modeSingle');
    const modeBulk = document.getElementById('modeBulk');
    const singleInputGroup = document.getElementById('singleInput');
    const bulkInputGroup = document.getElementById('bulkInput');
    const bulkSearchInput = document.getElementById('bulkSearchInput');
    const bulkResultsContainer = document.getElementById('bulkResults');

    if (modeSingle && modeBulk) {
        modeSingle.addEventListener('click', () => {
            bulkMode = false;
            modeSingle.classList.add('active');
            modeBulk.classList.remove('active');
            singleInputGroup.classList.remove('hidden');
            bulkInputGroup.classList.add('hidden');
            searchInput.required = true;
        });
        modeBulk.addEventListener('click', () => {
            bulkMode = true;
            modeBulk.classList.add('active');
            modeSingle.classList.remove('active');
            bulkInputGroup.classList.remove('hidden');
            singleInputGroup.classList.add('hidden');
            searchInput.required = false;
        });
    }

    // ============ Form Submit ============
    searchForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (bulkMode) {
            await handleBulkSearch();
            return;
        }

        let query = searchInput.value.trim();
        if (!query) return;

        // Prepend country code for phone searches if number doesn't already have + prefix
        if (currentType === 'phone' && phoneCountryCode && !query.startsWith('+')) {
            query = phoneCountryCode.value + query;
        }

        setLoading(true);
        hideError();
        hideResults();
        if (bulkResultsContainer) bulkResultsContainer.classList.add('hidden');

        try {
            const response = await fetch('/vignette/api/index.php?route=search', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    query_value: query,
                    query_type: currentType
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || data.error || 'Search failed');
            }

            renderResults(data);
        } catch (err) {
            showError(err.message || 'Failed to connect to Vignette API');
        } finally {
            setLoading(false);
        }
    });

    // ============ Bulk Search Handler ============
    async function handleBulkSearch() {
        const raw = bulkSearchInput ? bulkSearchInput.value.trim() : '';
        if (!raw) return;

        const lines = raw.split('\n').map(l => l.trim()).filter(l => l.length > 0);
        if (lines.length === 0) return;
        if (lines.length > 10) {
            showError('Maximum 10 queries per batch');
            return;
        }

        const queries = lines.map(line => ({ value: line, type: currentType }));

        // UI: show loading state
        hideError();
        hideResults();
        if (bulkResultsContainer) bulkResultsContainer.classList.add('hidden');
        setLoading(true);

        const bulkBtn = document.getElementById('bulkSearchBtn');
        const bulkBtnText = bulkBtn ? bulkBtn.querySelector('.btn-text') : null;
        const bulkBtnLoader = bulkBtn ? bulkBtn.querySelector('.btn-loader') : null;
        const bulkProgress = document.getElementById('bulkProgress');

        if (bulkBtnText) bulkBtnText.classList.add('hidden');
        if (bulkBtnLoader) bulkBtnLoader.classList.remove('hidden');
        if (bulkProgress) bulkProgress.textContent = `0/${queries.length}`;
        if (bulkBtn) bulkBtn.disabled = true;

        try {
            const response = await fetch('/vignette/api/index.php?route=bulk-search', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ queries })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || data.error || 'Bulk search failed');
            }

            renderBulkResults(data);
        } catch (err) {
            showError(err.message || 'Failed to run bulk search');
        } finally {
            setLoading(false);
            if (bulkBtnText) bulkBtnText.classList.remove('hidden');
            if (bulkBtnLoader) bulkBtnLoader.classList.add('hidden');
            if (bulkBtn) bulkBtn.disabled = false;
        }
    }

    // ============ Render Bulk Results ============
    function renderBulkResults(data) {
        if (loadingSkeleton) loadingSkeleton.classList.add('hidden');
        if (!bulkResultsContainer) return;

        bulkResultsContainer.classList.remove('hidden');
        bulkResultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // Summary stats
        const badge = document.getElementById('bulkBadge');
        if (badge) badge.textContent = `${data.completed}/${data.total} completed`;

        const statsBar = document.getElementById('bulkSummaryStats');
        if (statsBar) {
            const riskCls = data.avg_risk <= 20 ? 'success' : data.avg_risk <= 50 ? 'warning' : data.avg_risk <= 75 ? 'high' : 'danger';
            statsBar.innerHTML = `
                <div class="summary-stat"><div class="summary-stat-value accent">${data.total}</div><div class="summary-stat-label">Queries</div></div>
                <div class="summary-stat"><div class="summary-stat-value success">${data.completed}</div><div class="summary-stat-label">Success</div></div>
                <div class="summary-stat"><div class="summary-stat-value danger">${data.failed}</div><div class="summary-stat-label">Failed</div></div>
                <div class="summary-stat"><div class="summary-stat-value ${riskCls}">${data.avg_risk}/100</div><div class="summary-stat-label">Avg Risk</div></div>
            `;
        }

        // Results list
        const list = document.getElementById('bulkResultsList');
        if (!list) return;
        list.innerHTML = '';

        data.results.forEach((r, i) => {
            if (!r.success) {
                list.innerHTML += `
                    <div class="bulk-result-item failed">
                        <div class="bulk-result-avatar" style="display:flex;align-items:center;justify-content:center;font-size:1rem;color:var(--danger)">!</div>
                        <div class="bulk-result-info">
                            <div class="bulk-result-query">${escapeHtml(r.query_value)}</div>
                            <div class="bulk-result-meta">${escapeHtml(r.query_type)} &middot; ${escapeHtml(r.error || 'Failed')}</div>
                        </div>
                    </div>`;
                return;
            }

            const profile = r.profile;
            const name = profile.identity?.display_name || r.query_value;
            const avatar = profile.identity?.avatar_url || '';
            const risk = profile.risk?.score ?? 0;
            const level = profile.risk?.level || 'low';
            const sources = profile.meta?.sources_success || 0;
            const totalSources = (profile.meta?.sources_queried || []).length;
            const breaches = profile.breaches?.count || 0;

            const avatarHtml = avatar
                ? `<img class="bulk-result-avatar" src="${escapeHtml(avatar)}" alt="" onerror="this.style.display='none'">`
                : `<div class="bulk-result-avatar" style="display:flex;align-items:center;justify-content:center;font-size:0.8rem;color:var(--text-muted)">${escapeHtml(name.charAt(0).toUpperCase())}</div>`;

            list.innerHTML += `
                <div class="bulk-result-item" data-bulk-index="${i}">
                    ${avatarHtml}
                    <div class="bulk-result-info">
                        <div class="bulk-result-query">${escapeHtml(name)}</div>
                        <div class="bulk-result-meta">${escapeHtml(r.query_type)} &middot; ${sources}/${totalSources} sources &middot; ${breaches} breach${breaches !== 1 ? 'es' : ''}</div>
                    </div>
                    <div class="bulk-result-risk ${level}">${risk}</div>
                    <button class="bulk-result-expand" data-search-id="${r.search_id}">View Full</button>
                </div>`;
        });

        // Click handlers for "View Full" buttons
        list.querySelectorAll('.bulk-result-expand').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const searchId = btn.dataset.searchId;
                if (!searchId) return;

                btn.textContent = 'Loading...';
                btn.disabled = true;

                try {
                    const res = await fetch(`/vignette/api/index.php?route=replay&id=${searchId}`);
                    const replayData = await res.json();
                    if (replayData.success) {
                        bulkResultsContainer.classList.add('hidden');
                        renderResults(replayData);
                    } else {
                        showToast('Failed to load result', 'error');
                    }
                } catch (err) {
                    showToast('Failed to load result', 'error');
                } finally {
                    btn.textContent = 'View Full';
                    btn.disabled = false;
                }
            });
        });
    }

    // ============ Render Results ============
    function renderResults(data) {
        const profile = data.profile;
        if (!profile) {
            showError('No profile data returned');
            return;
        }

        if (loadingSkeleton) loadingSkeleton.classList.add('hidden');
        resultsContainer.classList.remove('hidden');

        // Smooth scroll to results
        resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // Store search_id and show export button
        lastSearchId = data.search_id || null;
        if (lastSearchId && exportBar) {
            exportBar.classList.remove('hidden');
        }

        // Show source-level errors/warnings
        if (data.source_errors && Object.keys(data.source_errors).length > 0) {
            const msgs = Object.entries(data.source_errors)
                .map(([src, err]) => `${src}: ${err}`).join('\n');
            showWarning(msgs);
        }

        // Profile Card
        renderProfileCard(profile);

        // Summary Stats Bar
        renderSummaryBar(profile);

        // AI Summary
        renderAiSummary(profile.ai_summary);

        // Timeline
        renderTimeline(profile);

        // Breach Card
        renderBreachCard(profile.breaches);

        // GitHub Card
        renderGitHubCard(profile.github, profile.repos, profile.social_links);

        // WHOIS Card
        renderWhoisCard(profile.whois_data);

        // DNS Card
        renderDnsCard(profile.dns_data);

        // SSL Card
        renderSslCard(profile.ssl_data);

        // VirusTotal Card
        renderVirusTotalCard(profile.virustotal_data);

        // Username OSINT Card
        renderUsernameCard(profile.username_profiles);

        // Phone Intelligence Card
        renderPhoneCard(profile.phone_data);

        // Google Search Card
        renderGoogleCard(profile.google_results);

        // IP Card
        renderIpCard(profile.ip_data);

        // Risk Card
        renderRiskCard(profile.risk);

        // Relationships Card
        renderRelationships(data.relationships);

        // Meta Bar
        renderMeta(profile.meta);

        // Check if any cards are actually visible
        const visibleCards = resultsContainer.querySelectorAll('.card:not(.hidden), .summary-bar:not(.hidden)');
        if (visibleCards.length === 0) {
            showError('No data found for this query. Try a different search type or term.');
            resultsContainer.classList.add('hidden');
        }
    }

    function renderSummaryBar(profile) {
        const bar = document.getElementById('summaryBar');
        const meta = profile.meta || {};
        const risk = profile.risk || {};
        const breaches = profile.breaches || {};
        const usernames = profile.username_profiles || {};
        const vt = profile.virustotal_data || {};

        const stats = [];

        // Sources
        stats.push({
            value: (meta.sources_success || 0) + '/' + ((meta.sources_success || 0) + (meta.sources_failed || 0)),
            label: 'Sources',
            cls: 'accent'
        });

        // Risk
        const riskCls = risk.score > 50 ? 'danger' : risk.score > 20 ? 'warning' : 'success';
        stats.push({ value: (risk.score || 0) + '/100', label: 'Risk Score', cls: riskCls });

        // Breaches (only if searched)
        if (breaches.count !== undefined) {
            stats.push({
                value: breaches.count || 0,
                label: 'Breaches',
                cls: breaches.count > 0 ? 'danger' : 'success'
            });
        }

        // Platforms found (username search)
        if (usernames.total_found !== undefined) {
            stats.push({
                value: usernames.total_found + '/' + usernames.total_checked,
                label: 'Platforms',
                cls: 'accent'
            });
        }

        // VirusTotal (domain/IP search)
        if (vt.total_engines) {
            const malCount = vt.malicious_count || 0;
            stats.push({
                value: malCount === 0 ? 'Clean' : malCount + ' flags',
                label: 'Threat Intel',
                cls: malCount > 0 ? 'danger' : 'success'
            });
        }

        // Email security (domain search)
        if (profile.dns_data && profile.dns_data.email_security) {
            const secScore = profile.dns_data.email_security.score || 0;
            const secCls = secScore >= 70 ? 'success' : secScore >= 40 ? 'warning' : 'danger';
            stats.push({ value: secScore + '/100', label: 'Email Security', cls: secCls });
        }

        if (stats.length === 0) {
            bar.classList.add('hidden');
            return;
        }

        bar.classList.remove('hidden');
        bar.innerHTML = stats.map(s =>
            `<div class="summary-stat">
                <div class="summary-stat-value ${s.cls}">${s.value}</div>
                <div class="summary-stat-label">${s.label}</div>
            </div>`
        ).join('');
    }

    function renderAiSummary(summary) {
        const card = document.getElementById('aiSummaryCard');
        const content = document.getElementById('aiSummaryContent');

        if (!summary) {
            card.classList.add('hidden');
            return;
        }

        card.classList.remove('hidden');
        // Convert markdown to HTML (basic: bold, italic, bullets, paragraphs)
        content.innerHTML = summary
            .split(/\n\n+/)
            .map(p => {
                let html = escapeHtml(p.trim());
                // Bold: **text**
                html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                // Italic: *text*
                html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
                // Bullet lines
                html = html.replace(/^[-•]\s+(.+)$/gm, '<li>$1</li>');
                if (html.includes('<li>')) {
                    html = '<ul>' + html + '</ul>';
                }
                return `<p>${html}</p>`;
            })
            .join('');
    }

    function renderProfileCard(profile) {
        const card = document.getElementById('profileCard');
        const identity = profile.identity || {};
        const risk = profile.risk || {};

        // Only show if we have meaningful data — always show for name/username/email searches
        const queryType = profile.query ? profile.query.type : '';
        const alwaysShow = ['name', 'username', 'email'].includes(queryType);
        if (!alwaysShow && !identity.display_name && !identity.avatar_url) {
            card.classList.add('hidden');
            return;
        }

        card.classList.remove('hidden');

        const avatar = document.getElementById('profileAvatar');
        if (identity.avatar_url) {
            avatar.src = identity.avatar_url;
            avatar.style.display = 'block';
        } else {
            avatar.style.display = 'none';
        }

        document.getElementById('profileName').textContent = identity.display_name || '';
        document.getElementById('profileBio').textContent = identity.bio || '';
        document.getElementById('profileCompany').textContent = identity.company || '';
        document.getElementById('profileLocation').textContent = identity.location ? '\u{1F4CD} ' + identity.location : '';

        // Risk badge
        const badge = document.getElementById('riskBadge');
        badge.querySelector('.risk-score').textContent = risk.score || 0;
        badge.className = 'risk-badge ' + (risk.level || 'clean');

        // Links
        const linksEl = document.getElementById('profileLinks');
        linksEl.innerHTML = '';
        const links = profile.social_links || {};
        for (const [platform, url] of Object.entries(links)) {
            if (url) {
                const a = document.createElement('a');
                a.href = url;
                a.target = '_blank';
                a.rel = 'noopener';
                a.className = 'profile-link';
                a.textContent = platform;
                linksEl.appendChild(a);
            }
        }

        // Emails/usernames
        (identity.emails || []).forEach(email => {
            const span = document.createElement('span');
            span.className = 'profile-link';
            span.textContent = email;
            linksEl.appendChild(span);
        });
    }

    function renderTimeline(profile) {
        const card = document.getElementById('timelineCard');
        const container = document.getElementById('timelineData');
        const events = [];

        // Helper: parse a date string and return a Date object, or null
        function parseDate(str) {
            if (!str) return null;
            const d = new Date(str);
            return isNaN(d.getTime()) ? null : d;
        }

        // Helper: format date nicely
        function formatDate(d) {
            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        // WHOIS dates
        if (profile.whois_data) {
            const w = profile.whois_data;
            const whoisDates = [
                { key: 'created_date', title: 'Domain Registered', desc: 'Domain was first registered' },
                { key: 'updated_date', title: 'Domain Record Updated', desc: 'WHOIS record was last updated' },
                { key: 'expiry_date', title: 'Domain Expiry', desc: 'Domain registration expires' }
            ];
            whoisDates.forEach(item => {
                const d = parseDate(w[item.key]);
                if (d) {
                    events.push({ date: d, title: item.title, description: item.desc, type: 'domain' });
                }
            });
        }

        // SSL dates
        if (profile.ssl_data) {
            const s = profile.ssl_data;
            const validFrom = parseDate(s.valid_from);
            const validTo = parseDate(s.valid_to);
            if (validFrom) {
                events.push({ date: validFrom, title: 'SSL Certificate Issued', description: 'Certificate issued by ' + (s.issuer || 'unknown CA'), type: 'security' });
            }
            if (validTo) {
                events.push({ date: validTo, title: 'SSL Certificate Expiry', description: 'Certificate expiration date', type: 'security' });
            }
        }

        // GitHub created_at
        if (profile.github && profile.github.created_at) {
            const d = parseDate(profile.github.created_at);
            if (d) {
                events.push({ date: d, title: 'GitHub Account Created', description: 'Member since ' + formatDate(d), type: 'social' });
            }
        }

        // Breaches
        if (profile.breaches && profile.breaches.items && profile.breaches.items.length > 0) {
            profile.breaches.items.forEach(b => {
                const d = parseDate(b.breach_date || b.BreachDate);
                if (d) {
                    events.push({ date: d, title: (b.name || b.Name || 'Unknown') + ' Data Breach', description: 'Exposed: ' + ((b.data_classes || b.DataClasses || []).slice(0, 3).join(', ') || 'various data'), type: 'breach' });
                }
            });
        }

        // VirusTotal last_analysis_date
        if (profile.virustotal_data && profile.virustotal_data.last_analysis_date) {
            const d = parseDate(profile.virustotal_data.last_analysis_date);
            if (d) {
                events.push({ date: d, title: 'VirusTotal Analysis', description: 'Last threat analysis scan', type: 'security' });
            }
        }

        // Current search timestamp
        if (profile.meta && profile.meta.timestamp) {
            const d = parseDate(profile.meta.timestamp);
            if (d) {
                events.push({ date: d, title: 'Vignette Search', description: 'This intelligence search was performed', type: 'search' });
            }
        }

        // Need at least 1 event to show the timeline
        if (events.length === 0) {
            card.classList.add('hidden');
            return;
        }

        // Sort chronologically, most recent first
        events.sort((a, b) => b.date - a.date);

        // Render
        let html = '<div class="timeline">';
        events.forEach(ev => {
            html += `
                <div class="timeline-event">
                    <div class="timeline-date">${formatDate(ev.date)}</div>
                    <div class="timeline-dot ${ev.type}"></div>
                    <div class="timeline-content">
                        <div class="timeline-title">${escapeHtml(ev.title)}</div>
                        <div class="timeline-desc">${escapeHtml(ev.description)}</div>
                    </div>
                </div>`;
        });
        html += '</div>';

        container.innerHTML = html;
        card.classList.remove('hidden');
    }

    function renderBreachCard(breaches) {
        const card = document.getElementById('breachCard');
        const list = document.getElementById('breachList');
        const countBadge = document.getElementById('breachCount');

        if (!breaches || !breaches.items || breaches.items.length === 0) {
            card.classList.add('hidden');
            return;
        }

        card.classList.remove('hidden');
        countBadge.textContent = breaches.count + ' breaches';
        countBadge.className = 'card-badge' + (breaches.count > 3 ? ' danger' : '');

        list.innerHTML = '';
        breaches.items.forEach(b => {
            const div = document.createElement('div');
            div.className = 'breach-item';

            const logo = b.logo_path
                ? `<img src="${escapeHtml(b.logo_path)}" alt="${escapeHtml(b.name)}">`
                : '<div style="width:32px;height:32px;background:var(--bg-secondary);border-radius:4px;"></div>';

            const classes = (b.data_classes || []).map(c => {
                const isDanger = ['Passwords', 'Password hints', 'Credit cards'].includes(c);
                return `<span class="breach-class-tag${isDanger ? ' danger' : ''}">${escapeHtml(c)}</span>`;
            }).join('');

            div.innerHTML = `
                ${logo}
                <div style="flex:1">
                    <div class="breach-name">${escapeHtml(b.title || b.name)}</div>
                    <div class="breach-date">Breached: ${escapeHtml(b.breach_date || 'Unknown')} &middot; ${(b.pwn_count || 0).toLocaleString()} accounts</div>
                    <div class="breach-classes">${classes}</div>
                </div>
            `;
            list.appendChild(div);
        });
    }

    function renderGitHubCard(github, repos, links) {
        const card = document.getElementById('githubCard');
        const body = document.getElementById('githubData');

        if (!github && (!repos || repos.length === 0)) {
            card.classList.add('hidden');
            return;
        }

        card.classList.remove('hidden');
        let html = '';

        if (github) {
            html += dataRow('Repositories', github.public_repos);
            html += dataRow('Followers', github.followers);
            html += dataRow('Following', github.following);
            if (github.company) html += dataRow('Company', github.company);
            if (github.created_at) html += dataRow('Member since', new Date(github.created_at).toLocaleDateString());
        }

        if (repos && repos.length > 0) {
            html += '<div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--border)">';
            html += '<div style="font-weight:600;margin-bottom:8px;color:var(--text-primary);font-size:0.85rem">Top Repositories</div>';
            repos.slice(0, 5).forEach(repo => {
                html += `<div class="repo-item">
                    <a href="${escapeHtml(repo.url)}" target="_blank" rel="noopener" class="repo-name">${escapeHtml(repo.name)}</a>
                    ${repo.description ? `<div class="repo-desc">${escapeHtml(repo.description)}</div>` : ''}
                    <div class="repo-meta">
                        ${repo.language ? `<span>${escapeHtml(repo.language)}</span>` : ''}
                        <span>\u2B50 ${repo.stars || 0}</span>
                        <span>\u{1F500} ${repo.forks || 0}</span>
                    </div>
                </div>`;
            });
            html += '</div>';
        }

        body.innerHTML = html;
    }

    function renderWhoisCard(whoisData) {
        const card = document.getElementById('whoisCard');
        const body = document.getElementById('whoisData');
        const privacyBadge = document.getElementById('whoisPrivacy');

        if (!whoisData || Object.keys(whoisData).length === 0) {
            card.classList.add('hidden');
            return;
        }

        card.classList.remove('hidden');
        let html = '';

        if (whoisData.domain) html += dataRow('Domain', whoisData.domain);
        if (whoisData.registrar) html += dataRow('Registrar', whoisData.registrar);
        if (whoisData.registrant_org) html += dataRow('Registrant', whoisData.registrant_org);
        if (whoisData.registrant_country) html += dataRow('Country', whoisData.registrant_country);
        if (whoisData.domain_age) html += dataRow('Domain Age', whoisData.domain_age);
        if (whoisData.dnssec) html += dataRow('DNSSEC', whoisData.dnssec);

        // Domain timeline bar
        if (whoisData.created_date && whoisData.expiry_date) {
            const created = new Date(whoisData.created_date).getTime();
            const expiry = new Date(whoisData.expiry_date).getTime();
            const now = Date.now();
            const total = expiry - created;
            const elapsed = now - created;
            const pct = total > 0 ? Math.min(Math.max((elapsed / total) * 100, 0), 100) : 0;
            const remaining = expiry > now ? Math.ceil((expiry - now) / (1000 * 60 * 60 * 24)) : 0;
            const barColor = remaining < 90 ? 'var(--danger)' : remaining < 365 ? 'var(--warning)' : 'var(--accent)';

            html += `<div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--border)">
                <div style="font-weight:600;margin-bottom:8px;color:var(--text-primary);font-size:0.85rem">Domain Lifecycle</div>
                <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:var(--text-muted);margin-bottom:4px">
                    <span>${escapeHtml(whoisData.created_date)}</span>
                    <span>${remaining > 0 ? remaining + ' days left' : 'EXPIRED'}</span>
                    <span>${escapeHtml(whoisData.expiry_date)}</span>
                </div>
                <div style="height:6px;background:var(--bg-secondary);border-radius:3px;overflow:hidden">
                    <div style="width:${pct.toFixed(1)}%;height:100%;background:${barColor};border-radius:3px;transition:width 0.5s"></div>
                </div>
            </div>`;
        }

        // Name servers (expandable)
        if (whoisData.name_servers && whoisData.name_servers.length > 0) {
            const maxNs = 3;
            const nsId = 'whoisNsList';
            html += '<div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">';
            html += '<div style="font-weight:600;margin-bottom:6px;color:var(--text-primary);font-size:0.85rem">Name Servers</div>';
            whoisData.name_servers.forEach((ns, i) => {
                const hidden = i >= maxNs ? ` style="display:none" data-expandable="${nsId}"` : '';
                html += `<div${hidden} style="${i >= maxNs ? 'display:none;' : ''}font-size:0.82rem;color:var(--text-secondary);padding:2px 0;font-family:monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" ${i >= maxNs ? `data-expandable="${nsId}"` : ''}>${escapeHtml(ns)}</div>`;
            });
            if (whoisData.name_servers.length > maxNs) {
                html += `<button onclick="toggleExpand('${nsId}', this)" style="background:none;border:none;color:var(--accent);font-size:0.75rem;cursor:pointer;padding:4px 0">Show ${whoisData.name_servers.length - maxNs} more</button>`;
            }
            html += '</div>';
        }

        // Status flags (expandable)
        if (whoisData.status && whoisData.status.length > 0) {
            const maxStatus = 3;
            const statusId = 'whoisStatusList';
            html += '<div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);display:flex;gap:6px;flex-wrap:wrap;align-items:center">';
            whoisData.status.forEach((s, i) => {
                const label = s.replace(/\s*https?:\/\/.*$/, '').trim();
                const hidden = i >= maxStatus ? `data-expandable="${statusId}" style="display:none"` : '';
                html += `<span class="tag-safe" style="${i >= maxStatus ? 'display:none;' : ''}font-size:0.75rem" ${i >= maxStatus ? `data-expandable="${statusId}"` : ''}>${escapeHtml(label)}</span>`;
            });
            if (whoisData.status.length > maxStatus) {
                html += `<button onclick="toggleExpand('${statusId}', this)" style="background:none;border:none;color:var(--accent);font-size:0.75rem;cursor:pointer;padding:2px 4px">+${whoisData.status.length - maxStatus}</button>`;
            }
            html += '</div>';
        }

        // Privacy badge
        if (whoisData.is_privacy_protected) {
            privacyBadge.classList.remove('hidden');
        } else {
            privacyBadge.classList.add('hidden');
        }

        body.innerHTML = html;
    }

    function renderDnsCard(dnsData) {
        const card = document.getElementById('dnsCard');
        const body = document.getElementById('dnsData');

        if (!dnsData || Object.keys(dnsData).length === 0) {
            card.classList.add('hidden');
            return;
        }

        card.classList.remove('hidden');
        let html = '';

        // Mail provider
        if (dnsData.mail_provider) {
            html += dataRow('Mail Provider', dnsData.mail_provider);
        }

        // Hosting/CDN
        if (dnsData.hosting && dnsData.hosting.length > 0) {
            html += dataRow('Infrastructure', dnsData.hosting.join(', '));
        }

        // MX records
        if (dnsData.mx_records && dnsData.mx_records.length > 0) {
            html += '<div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">';
            html += '<div style="font-weight:600;margin-bottom:6px;color:var(--text-primary);font-size:0.85rem">MX Records</div>';
            dnsData.mx_records.slice(0, 5).forEach(mx => {
                html += `<div style="font-size:0.82rem;color:var(--text-secondary);padding:2px 0;font-family:monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <span style="color:var(--text-muted);min-width:24px;display:inline-block">${mx.priority}</span> ${escapeHtml(mx.host)}
                </div>`;
            });
            html += '</div>';
        }

        // A records
        if (dnsData.a_records && dnsData.a_records.length > 0) {
            html += '<div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">';
            html += '<div style="font-weight:600;margin-bottom:6px;color:var(--text-primary);font-size:0.85rem">A Records</div>';
            dnsData.a_records.slice(0, 4).forEach(ip => {
                html += `<div style="font-size:0.82rem;color:var(--text-secondary);padding:2px 0;font-family:monospace">${escapeHtml(ip)}</div>`;
            });
            html += '</div>';
        }

        // Email security
        if (dnsData.email_security) {
            const sec = dnsData.email_security;
            html += '<div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">';
            html += '<div style="font-weight:600;margin-bottom:6px;color:var(--text-primary);font-size:0.85rem">Email Security</div>';

            const secScore = sec.score || 0;
            const secColor = secScore >= 70 ? 'var(--success)' : secScore >= 40 ? 'var(--warning)' : 'var(--danger)';
            html += `<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                <div style="flex:1;height:4px;background:var(--bg-secondary);border-radius:2px;overflow:hidden">
                    <div style="width:${secScore}%;height:100%;background:${secColor};border-radius:2px"></div>
                </div>
                <span style="font-size:0.75rem;font-weight:600;color:${secColor}">${secScore}/100</span>
            </div>`;

            html += `<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px">`;
            html += sec.spf ? '<span class="tag-safe">SPF</span>' : '<span class="tag-danger">No SPF</span>';
            html += sec.dmarc ? '<span class="tag-safe">DMARC</span>' : '<span class="tag-danger">No DMARC</span>';
            html += sec.has_dkim ? '<span class="tag-safe">DKIM</span>' : '<span style="background:var(--warning-dim);color:var(--warning);padding:2px 8px;border-radius:4px;font-size:0.75rem">DKIM unknown</span>';
            html += '</div>';

            if (sec.issues && sec.issues.length > 0) {
                sec.issues.forEach(issue => {
                    html += `<div style="font-size:0.75rem;color:var(--text-muted);padding:2px 0">&bull; ${escapeHtml(issue)}</div>`;
                });
            }
            html += '</div>';
        }

        // Service verifications
        if (dnsData.verifications && dnsData.verifications.length > 0) {
            html += '<div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">';
            html += '<div style="font-weight:600;margin-bottom:6px;color:var(--text-primary);font-size:0.85rem">Verified Services</div>';
            html += '<div style="display:flex;gap:6px;flex-wrap:wrap">';
            dnsData.verifications.forEach(svc => {
                html += `<span style="background:var(--accent-dim);color:var(--accent);padding:2px 8px;border-radius:4px;font-size:0.75rem">${escapeHtml(svc)}</span>`;
            });
            html += '</div></div>';
        }

        // TXT records (expandable)
        if (dnsData.txt_records && dnsData.txt_records.length > 0) {
            const txtId = 'dnsTxtList';
            html += '<div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">';
            html += `<button onclick="toggleExpand('${txtId}', this)" style="background:none;border:none;color:var(--accent);font-size:0.8rem;cursor:pointer;padding:0;font-weight:600">TXT Records (${dnsData.txt_records.length})</button>`;
            dnsData.txt_records.forEach(txt => {
                html += `<div data-expandable="${txtId}" style="display:none;font-size:0.75rem;color:var(--text-muted);padding:4px 0;font-family:monospace;word-break:break-all">${escapeHtml(txt)}</div>`;
            });
            html += '</div>';
        }

        body.innerHTML = html;
    }

    function renderSslCard(sslData) {
        const card = document.getElementById('sslCard');
        const body = document.getElementById('sslData');
        const badge = document.getElementById('sslBadge');

        if (!sslData || Object.keys(sslData).length === 0) {
            card.classList.add('hidden');
            return;
        }

        card.classList.remove('hidden');
        let html = '';

        // Badge (clear stale inline styles first)
        badge.removeAttribute('style');
        if (sslData.is_expired) {
            badge.textContent = 'EXPIRED';
            badge.className = 'card-badge danger';
        } else if (sslData.is_expiring_soon) {
            badge.textContent = 'Expiring Soon';
            badge.className = 'card-badge warning';
        } else {
            badge.textContent = 'Valid';
            badge.className = 'card-badge';
        }
        badge.classList.remove('hidden');

        if (sslData.subject_cn) html += dataRow('Common Name', sslData.subject_cn);
        if (sslData.certificate_authority) html += dataRow('Certificate Authority', sslData.certificate_authority);
        if (sslData.cert_type) html += dataRow('Certificate Type', sslData.cert_type);
        if (sslData.signature_algorithm) html += dataRow('Signature', sslData.signature_algorithm);
        if (sslData.key_type && sslData.key_bits) html += dataRow('Key', sslData.key_type + ' ' + sslData.key_bits + '-bit');

        // SSL validity timeline
        if (sslData.valid_from && sslData.valid_to) {
            const from = new Date(sslData.valid_from).getTime();
            const to = new Date(sslData.valid_to).getTime();
            const now = Date.now();
            const total = to - from;
            const elapsed = now - from;
            const pct = total > 0 ? Math.min(Math.max((elapsed / total) * 100, 0), 100) : 0;
            const daysLeft = sslData.days_remaining || 0;
            const barColor = sslData.is_expired ? 'var(--danger)' : daysLeft < 30 ? 'var(--warning)' : 'var(--success)';

            html += `<div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--border)">
                <div style="font-weight:600;margin-bottom:8px;color:var(--text-primary);font-size:0.85rem">Certificate Validity</div>
                <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:var(--text-muted);margin-bottom:4px">
                    <span>${escapeHtml(sslData.valid_from)}</span>
                    <span>${daysLeft > 0 ? daysLeft + ' days left' : 'EXPIRED'}</span>
                    <span>${escapeHtml(sslData.valid_to)}</span>
                </div>
                <div style="height:6px;background:var(--bg-secondary);border-radius:3px;overflow:hidden">
                    <div style="width:${pct.toFixed(1)}%;height:100%;background:${barColor};border-radius:3px"></div>
                </div>
            </div>`;
        }

        // SAN domains
        if (sslData.san_domains && sslData.san_domains.length > 0) {
            const sanId = 'sslSanList';
            const maxSan = 5;
            html += '<div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">';
            html += `<div style="font-weight:600;margin-bottom:6px;color:var(--text-primary);font-size:0.85rem">Subject Alt Names (${sslData.san_count})</div>`;
            html += '<div style="display:flex;gap:4px;flex-wrap:wrap">';
            sslData.san_domains.forEach((san, i) => {
                const hidden = i >= maxSan ? `data-expandable="${sanId}" style="display:none"` : '';
                const isWildcard = san.startsWith('*.');
                const tagStyle = isWildcard ? 'background:var(--warning-dim);color:var(--warning)' : 'background:var(--bg-secondary);color:var(--text-secondary)';
                html += `<span ${hidden} style="${i >= maxSan ? 'display:none;' : ''}${tagStyle};padding:2px 8px;border-radius:4px;font-size:0.72rem;font-family:monospace" ${i >= maxSan ? `data-expandable="${sanId}"` : ''}>${escapeHtml(san)}</span>`;
            });
            if (sslData.san_domains.length > maxSan) {
                html += `<button onclick="toggleExpand('${sanId}', this)" style="background:none;border:none;color:var(--accent);font-size:0.75rem;cursor:pointer;padding:2px 4px">+${sslData.san_domains.length - maxSan} more</button>`;
            }
            html += '</div></div>';
        }

        // Certificate chain
        if (sslData.chain && sslData.chain.length > 1) {
            html += '<div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">';
            html += '<div style="font-weight:600;margin-bottom:6px;color:var(--text-primary);font-size:0.85rem">Certificate Chain</div>';
            sslData.chain.forEach((cert, i) => {
                const indent = i * 12;
                html += `<div style="font-size:0.75rem;color:var(--text-secondary);padding:2px 0;padding-left:${indent}px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    ${i > 0 ? '&#8627; ' : ''}${escapeHtml(cert.subject)}
                </div>`;
            });
            html += '</div>';
        }

        body.innerHTML = html;
    }

    function renderVirusTotalCard(vtData) {
        const card = document.getElementById('vtCard');
        const body = document.getElementById('vtData');
        const badge = document.getElementById('vtBadge');

        if (!vtData || Object.keys(vtData).length === 0) {
            card.classList.add('hidden');
            return;
        }

        card.classList.remove('hidden');
        let html = '';

        const malicious = vtData.malicious_count || 0;
        const suspicious = vtData.suspicious_count || 0;
        const harmless = vtData.harmless_count || 0;
        const undetected = vtData.undetected_count || 0;
        const total = vtData.total_engines || (malicious + suspicious + harmless + undetected);

        // Badge (clear stale inline styles first)
        badge.removeAttribute('style');
        if (malicious > 0) {
            badge.textContent = malicious + ' malicious';
            badge.className = 'card-badge danger';
        } else if (suspicious > 0) {
            badge.textContent = suspicious + ' suspicious';
            badge.className = 'card-badge warning';
        } else {
            badge.textContent = 'Clean';
            badge.className = 'card-badge';
        }
        badge.classList.remove('hidden');

        // Detection bar
        const malPct = total > 0 ? (malicious / total) * 100 : 0;
        const susPct = total > 0 ? (suspicious / total) * 100 : 0;
        const safePct = total > 0 ? ((harmless + undetected) / total) * 100 : 100;

        html += `<div style="margin-bottom:12px">
            <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:var(--text-muted);margin-bottom:4px">
                <span>${malicious} malicious</span>
                <span>${suspicious} suspicious</span>
                <span>${harmless + undetected} clean</span>
            </div>
            <div style="height:8px;background:var(--bg-secondary);border-radius:4px;overflow:hidden;display:flex">
                ${malPct > 0 ? `<div style="width:${malPct}%;background:var(--danger)"></div>` : ''}
                ${susPct > 0 ? `<div style="width:${susPct}%;background:var(--warning)"></div>` : ''}
                <div style="width:${safePct}%;background:var(--success)"></div>
            </div>
            <div style="text-align:center;font-size:0.75rem;color:var(--text-muted);margin-top:4px">${total} security engines scanned</div>
        </div>`;

        if (vtData.reputation !== undefined) html += dataRow('Reputation Score', vtData.reputation);
        if (vtData.domain) html += dataRow('Domain', vtData.domain);
        if (vtData.ip) html += dataRow('IP', vtData.ip);
        if (vtData.country) html += dataRow('Country', vtData.country);
        if (vtData.as_owner) html += dataRow('AS Owner', vtData.as_owner);
        if (vtData.network) html += dataRow('Network', vtData.network);
        if (vtData.registrar) html += dataRow('Registrar', vtData.registrar);

        // Categories
        if (vtData.categories && Object.keys(vtData.categories).length > 0) {
            const cats = [...new Set(Object.values(vtData.categories))];
            html += '<div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">';
            html += '<div style="font-weight:600;margin-bottom:6px;color:var(--text-primary);font-size:0.85rem">Categories</div>';
            html += '<div style="display:flex;gap:4px;flex-wrap:wrap">';
            cats.slice(0, 8).forEach(cat => {
                html += `<span style="background:var(--bg-secondary);color:var(--text-secondary);padding:2px 8px;border-radius:4px;font-size:0.72rem">${escapeHtml(cat)}</span>`;
            });
            html += '</div></div>';
        }

        // Popularity ranks
        if (vtData.popularity_ranks && Object.keys(vtData.popularity_ranks).length > 0) {
            html += '<div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">';
            html += '<div style="font-weight:600;margin-bottom:6px;color:var(--text-primary);font-size:0.85rem">Popularity</div>';
            for (const [source, info] of Object.entries(vtData.popularity_ranks)) {
                const rank = typeof info === 'object' ? (info.rank || info) : info;
                html += dataRow(escapeHtml(source), '#' + rank);
            }
            html += '</div>';
        }

        body.innerHTML = html;
    }

    function renderUsernameCard(profileData) {
        const card = document.getElementById('usernameCard');
        const body = document.getElementById('usernameData');
        const badge = document.getElementById('usernameBadge');

        if (!profileData || (!profileData.profiles && !profileData.found_on)) {
            card.classList.add('hidden');
            return;
        }

        card.classList.remove('hidden');
        const found = profileData.total_found || 0;
        const checked = profileData.total_checked || 0;
        badge.textContent = found + '/' + checked + ' found';
        badge.className = 'card-badge';

        // Update card title for name searches
        const titleEl = card.querySelector('.card-title');
        if (titleEl && profileData.searched_variants && profileData.searched_variants.length > 1) {
            titleEl.textContent = 'Social Profiles';
        } else if (titleEl) {
            titleEl.textContent = 'Username Discovery';
        }

        let html = '';

        // Summary bar
        const foundPct = checked > 0 ? (found / checked) * 100 : 0;
        html += `<div style="margin-bottom:12px">
            <div style="height:6px;background:var(--bg-secondary);border-radius:3px;overflow:hidden">
                <div style="width:${foundPct}%;height:100%;background:var(--accent);border-radius:3px"></div>
            </div>
            <div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px">Found on ${found} of ${checked} platforms</div>
        </div>`;

        // Show searched username variants (for name searches)
        if (profileData.searched_variants && profileData.searched_variants.length > 1) {
            html += `<div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:10px;padding:6px 10px;background:var(--bg-secondary);border-radius:var(--radius)">
                Usernames checked: ${profileData.searched_variants.map(v => `<span style="color:var(--accent);font-family:var(--font-mono)">${escapeHtml(v)}</span>`).join(', ')}
            </div>`;
        }

        // Found profiles
        if (profileData.profiles && profileData.profiles.length > 0) {
            const foundProfiles = profileData.profiles.filter(p => p.exists);
            const notFound = profileData.profiles.filter(p => !p.exists);

            if (foundProfiles.length > 0) {
                // Sort: web-verified profiles first
                foundProfiles.sort((a, b) => (b.web_verified ? 1 : 0) - (a.web_verified ? 1 : 0));
                foundProfiles.forEach(p => {
                    const verifiedBadge = p.web_verified
                        ? `<span style="background:var(--success-dim);color:var(--success);font-size:0.65rem;padding:1px 6px;border-radius:3px;margin-left:6px;font-weight:600">Web Verified</span>`
                        : '';
                    html += `<a href="${escapeHtml(p.url)}" target="_blank" rel="noopener" class="username-profile-link">
                        <span class="username-platform-name">${escapeHtml(p.platform)}${verifiedBadge}</span>
                        <span class="username-profile-url">${escapeHtml(p.url)}</span>
                        <span class="username-visit">Visit &rarr;</span>
                    </a>`;
                });
            }

            // Not found (collapsed)
            if (notFound.length > 0) {
                const nfId = 'usernameNotFound';
                html += `<button onclick="toggleExpand('${nfId}', this)" style="background:none;border:none;color:var(--text-muted);font-size:0.75rem;cursor:pointer;padding:0;margin-top:8px">Not found on ${notFound.length} platforms</button>`;
                html += `<div data-expandable="${nfId}" data-expand-display="flex" style="display:none;margin-top:6px;gap:6px;flex-wrap:wrap">`;
                notFound.forEach(p => {
                    html += `<span style="background:var(--bg-secondary);color:var(--text-muted);padding:2px 8px;border-radius:4px;font-size:0.72rem">${escapeHtml(p.platform)}</span>`;
                });
                html += '</div>';
            }
        }

        body.innerHTML = html;
    }

    function renderPhoneCard(phoneData) {
        const card = document.getElementById('phoneCard');
        const body = document.getElementById('phoneData');

        if (!phoneData || Object.keys(phoneData).length === 0) {
            card.classList.add('hidden');
            return;
        }

        card.classList.remove('hidden');
        let html = '';

        // Validity + type + carrier badges
        if (phoneData.valid !== undefined) {
            html += `<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap">
                <span class="${phoneData.valid ? 'tag-safe' : 'tag-danger'}">${phoneData.valid ? 'Valid Number' : 'Invalid Number'}</span>
                ${phoneData.type && phoneData.type !== 'unknown' ? `<span style="background:var(--bg-secondary);color:var(--text-secondary);padding:2px 8px;border-radius:4px;font-size:0.75rem">${escapeHtml(phoneData.type.charAt(0).toUpperCase() + phoneData.type.slice(1))}</span>` : ''}
                ${phoneData.carrier ? `<span style="background:var(--accent-dim);color:var(--accent);padding:2px 8px;border-radius:4px;font-size:0.75rem">${escapeHtml(phoneData.carrier)}</span>` : ''}
                ${phoneData.api_verified ? `<span style="background:var(--success-dim);color:var(--success);padding:2px 8px;border-radius:4px;font-size:0.65rem;font-weight:600">API Verified</span>` : ''}
            </div>`;
        }

        // Core info
        if (phoneData.formatted) html += dataRow('Formatted', phoneData.formatted);
        if (phoneData.country_code) html += dataRow('Country Code', phoneData.country_code);
        if (phoneData.country) html += dataRow('Country', phoneData.country + (phoneData.country_iso ? ' (' + phoneData.country_iso + ')' : ''));
        if (phoneData.region) html += dataRow('Region', phoneData.region);
        if (phoneData.carrier) html += dataRow('Carrier/Operator', phoneData.carrier);
        if (phoneData.national_number) html += dataRow('National Number', phoneData.national_number);
        if (phoneData.type) html += dataRow('Line Type', phoneData.type.charAt(0).toUpperCase() + phoneData.type.slice(1));

        // Formats section
        if (phoneData.formats) {
            html += `<div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--border)">
                <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:6px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px">Number Formats</div>`;
            if (phoneData.formats.e164) html += dataRow('E.164', phoneData.formats.e164);
            if (phoneData.formats.international) html += dataRow('International', phoneData.formats.international);
            if (phoneData.formats.local) html += dataRow('Local Format', phoneData.formats.local);
            if (phoneData.formats.national) html += dataRow('National', phoneData.formats.national);
            if (phoneData.formats.rfc3966) html += dataRow('RFC 3966', phoneData.formats.rfc3966);
            html += '</div>';
        }

        // Spam check
        if (phoneData.spam && phoneData.spam.flags && phoneData.spam.flags.length > 0) {
            const spamLevel = phoneData.spam.risk_level || 'unknown';
            const spamColor = spamLevel === 'high' ? 'var(--danger)' : spamLevel === 'medium' ? 'var(--warning)' : spamLevel === 'low' ? 'var(--success)' : 'var(--text-muted)';
            const spamBg = spamLevel === 'high' ? 'var(--danger-dim)' : spamLevel === 'medium' ? 'var(--warning-dim)' : spamLevel === 'low' ? 'var(--success-dim)' : 'var(--bg-secondary)';
            html += `<div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--border)">
                <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:6px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px">Spam/Scam Check</div>
                <div style="display:inline-block;padding:2px 10px;border-radius:12px;font-size:0.75rem;font-weight:600;background:${spamBg};color:${spamColor};margin-bottom:8px">${escapeHtml(spamLevel.charAt(0).toUpperCase() + spamLevel.slice(1))} Risk</div>`;
            phoneData.spam.flags.forEach(flag => {
                html += `<div style="font-size:0.8rem;color:var(--text-secondary);margin:3px 0;padding-left:12px;border-left:2px solid ${spamColor}">${escapeHtml(flag)}</div>`;
            });
            html += '</div>';
        }

        // Messaging apps
        if (phoneData.messaging) {
            html += `<div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--border)">
                <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:8px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px">Messaging Apps</div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">`;
            if (phoneData.messaging.whatsapp) {
                html += `<a href="${escapeHtml(phoneData.messaging.whatsapp.check_url)}" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius);color:var(--text-primary);text-decoration:none;font-size:0.8rem;transition:border-color 0.2s">
                    <span style="color:#25D366;font-weight:700">WhatsApp</span> Check &rarr;
                </a>`;
            }
            if (phoneData.messaging.telegram) {
                html += `<a href="${escapeHtml(phoneData.messaging.telegram.check_url)}" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:var(--bg-secondary);border:1px solid var(--border);border-radius:var(--radius);color:var(--text-primary);text-decoration:none;font-size:0.8rem;transition:border-color 0.2s">
                    <span style="color:#0088cc;font-weight:700">Telegram</span> Check &rarr;
                </a>`;
            }
            html += '</div></div>';
        }

        body.innerHTML = html;
    }

    function renderGoogleCard(googleData) {
        const card = document.getElementById('googleCard');
        const body = document.getElementById('googleData');
        const badge = document.getElementById('googleBadge');

        if (!googleData || !googleData.results || googleData.results.length === 0) {
            if (googleData === null || googleData === undefined) {
                card.classList.add('hidden');
            }
            return;
        }

        card.classList.remove('hidden');
        badge.textContent = googleData.total_results ? googleData.total_results + ' results' : '';

        let html = '';

        if (googleData.search_time) {
            html += `<div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:10px">${googleData.total_results || 0} results (${googleData.search_time}s)</div>`;
        }

        googleData.results.slice(0, 8).forEach(r => {
            html += `<div style="padding:8px 0;border-bottom:1px solid var(--border)">
                <a href="${escapeHtml(r.url)}" target="_blank" rel="noopener" style="color:var(--accent);font-size:0.85rem;text-decoration:none;font-weight:500">${escapeHtml(r.title)}</a>
                <div style="font-size:0.72rem;color:var(--success);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escapeHtml(r.display_url || r.url)}</div>
                ${r.snippet ? `<div style="font-size:0.78rem;color:var(--text-secondary);margin-top:4px;line-height:1.4">${escapeHtml(r.snippet)}</div>` : ''}
            </div>`;
        });

        body.innerHTML = html;
    }

    function renderIpCard(ipData) {
        const card = document.getElementById('ipCard');
        const body = document.getElementById('ipData');

        if (!ipData || Object.keys(ipData).length === 0) {
            card.classList.add('hidden');
            return;
        }

        card.classList.remove('hidden');
        let html = '';

        if (ipData.ip) html += dataRow('IP Address', ipData.ip);
        if (ipData.hostname) html += dataRow('Hostname', ipData.hostname);
        if (ipData.city) html += dataRow('Location', `${ipData.city}, ${ipData.region || ''}, ${ipData.country || ''}`);
        if (ipData.org) html += dataRow('Organization', ipData.org);
        if (ipData.timezone) html += dataRow('Timezone', ipData.timezone);
        if (ipData.postal) html += dataRow('Postal Code', ipData.postal);

        // Security indicators
        html += '<div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap">';
        html += ipData.is_vpn ? '<span class="tag-danger">VPN Detected</span>' : '<span class="tag-safe">No VPN</span>';
        html += ipData.is_proxy ? '<span class="tag-danger">Proxy Detected</span>' : '<span class="tag-safe">No Proxy</span>';
        html += ipData.is_tor ? '<span class="tag-danger">Tor Exit Node</span>' : '<span class="tag-safe">No Tor</span>';
        html += '</div>';

        if (ipData.resolved_domain) {
            html += `<div style="margin-top:8px;font-size:0.8rem;color:var(--text-muted)">Resolved from: ${escapeHtml(ipData.resolved_domain)}</div>`;
        }

        body.innerHTML = html;
    }

    function renderRiskCard(risk) {
        const card = document.getElementById('riskCard');
        const body = document.getElementById('riskFactors');

        if (!risk || !risk.factors || risk.factors.length === 0) {
            card.classList.add('hidden');
            return;
        }

        card.classList.remove('hidden');
        let html = '';

        // Risk score bar
        const pct = Math.min(risk.score || 0, 100);
        const barColor = pct > 75 ? 'var(--danger)' : pct > 50 ? '#f97316' : pct > 20 ? 'var(--warning)' : 'var(--success)';
        html += `<div style="margin-bottom:14px">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                <span style="font-size:0.85rem;font-weight:600">${risk.level ? risk.level.toUpperCase() : 'CLEAN'}</span>
                <span style="font-size:0.85rem;font-weight:600">${pct}/100</span>
            </div>
            <div style="height:6px;background:var(--bg-secondary);border-radius:3px;overflow:hidden">
                <div style="width:${pct}%;height:100%;background:${barColor};border-radius:3px;transition:width 0.5s"></div>
            </div>
        </div>`;

        risk.factors.forEach(f => {
            const fl = f.toLowerCase();
            let cls = 'info';
            if (fl.includes('password') || fl.includes('sensitive') || fl.includes('tor') || fl.includes('expired') || fl.includes('no ssl') || fl.includes('very new')) {
                cls = 'danger';
            } else if (fl.includes('breach') || fl.includes('vpn') || fl.includes('proxy') || fl.includes('spoof') || fl.includes('no spf') || fl.includes('weak') || fl.includes('less than')) {
                cls = 'warning';
            }
            html += `<div class="risk-factor ${cls}">${escapeHtml(f)}</div>`;
        });

        body.innerHTML = html;
    }

    function renderMeta(meta) {
        if (!meta) return;
        const bar = document.getElementById('metaBar');
        bar.classList.remove('hidden');
        let text = `Sources: ${meta.sources_success || 0} successful`;
        if (meta.sources_failed > 0) {
            text += `, ${meta.sources_failed} failed`;
        }
        document.getElementById('metaSources').textContent = text;
        document.getElementById('metaTimestamp').textContent = meta.timestamp ? new Date(meta.timestamp).toLocaleString() : '';
    }

    // ============ Helpers ============
    function dataRow(label, value) {
        const val = String(value ?? '');
        return `<div class="data-row">
            <span class="data-label">${escapeHtml(label)}</span>
            <span class="data-value" title="${escapeHtml(val)}">${escapeHtml(val)}</span>
        </div>`;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function setLoading(loading) {
        searchBtn.disabled = loading;
        btnText.classList.toggle('hidden', loading);
        btnLoader.classList.toggle('hidden', !loading);
        if (loadingSkeleton) {
            loadingSkeleton.classList.toggle('hidden', !loading);
        }
    }

    function showError(msg) {
        errorDisplay.textContent = msg;
        errorDisplay.classList.remove('hidden');
    }

    function showWarning(msg) {
        let el = document.getElementById('warningDisplay');
        if (!el) {
            el = document.createElement('div');
            el.id = 'warningDisplay';
            el.className = 'warning-display';
            resultsContainer.prepend(el);
        }
        el.textContent = msg;
        el.classList.remove('hidden');
    }

    function hideError() {
        errorDisplay.classList.add('hidden');
        const w = document.getElementById('warningDisplay');
        if (w) w.classList.add('hidden');
    }

    function renderRelationships(relationships) {
        const card = document.getElementById('relationshipsCard');
        const body = document.getElementById('relationshipsData');
        const badge = document.getElementById('relationshipsBadge');

        if (!relationships || relationships.length === 0) {
            card.classList.add('hidden');
            return;
        }

        card.classList.remove('hidden');
        badge.textContent = relationships.length + ' connection' + (relationships.length !== 1 ? 's' : '');

        const typeIcons = {
            shared_email: '&#9993;',
            shared_username: '&#128100;',
            shared_ip: '&#127760;',
            shared_registrar: '&#128203;',
            shared_hosting: '&#9729;',
            shared_ssl_ca: '&#128274;',
            same_person: '&#128101;',
        };

        const typeLabels = {
            shared_email: 'Shared Email',
            shared_username: 'Shared Username',
            shared_ip: 'Shared IP',
            shared_registrar: 'Shared Registrar',
            shared_hosting: 'Shared Hosting',
            shared_ssl_ca: 'Shared SSL CA',
            same_person: 'Same Person',
        };

        const confidenceColors = {
            high: 'var(--danger, #ef4444)',
            medium: 'var(--warning, #f59e0b)',
            low: 'var(--text-muted, #6b7280)',
        };

        let html = '';
        relationships.forEach(function (rel) {
            const icon = typeIcons[rel.type] || '&#128279;';
            const label = typeLabels[rel.type] || rel.type;
            const confColor = confidenceColors[rel.confidence] || confidenceColors.low;
            const confLabel = rel.confidence.charAt(0).toUpperCase() + rel.confidence.slice(1);

            html += '<div class="relationship-item" style="display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid var(--border, #333);cursor:pointer" '
                + 'onclick="window.vignetteLoadSearch(\'' + escapeHtml(rel.related_query) + '\', \'' + escapeHtml(rel.related_query_type) + '\')" '
                + 'title="Click to search for ' + escapeHtml(rel.related_query) + '">'
                + '<div style="font-size:1.3rem;line-height:1;flex-shrink:0;margin-top:2px">' + icon + '</div>'
                + '<div style="flex:1;min-width:0">'
                + '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:2px">'
                + '<span style="font-weight:600;font-size:0.85rem;color:var(--text-primary, #e5e7eb)">' + escapeHtml(label) + '</span>'
                + '<span style="font-size:0.7rem;padding:1px 6px;border-radius:3px;background:' + confColor + '20;color:' + confColor + ';font-weight:500">' + confLabel + '</span>'
                + '<span style="font-size:0.7rem;padding:1px 6px;border-radius:3px;background:var(--accent-dim, #0e3a5e);color:var(--accent, #00d4ff)">' + escapeHtml(rel.related_query_type) + '</span>'
                + '</div>'
                + '<div style="font-size:0.8rem;color:var(--text-secondary, #9ca3af);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escapeHtml(rel.description) + '</div>'
                + '<div style="font-size:0.75rem;color:var(--accent, #00d4ff);margin-top:2px">' + escapeHtml(rel.related_query) + '</div>'
                + '</div>'
                + '<div style="flex-shrink:0;color:var(--text-muted, #6b7280);font-size:0.9rem">&#8594;</div>'
                + '</div>';
        });

        body.innerHTML = html;
    }

    // Global helper so relationship items can trigger a new search for the related entity
    window.vignetteLoadSearch = function (queryValue, queryType) {
        searchInput.value = queryValue;
        var matchBtn = document.querySelector('.type-btn[data-type="' + queryType + '"]');
        if (matchBtn) matchBtn.click();
        searchForm.dispatchEvent(new Event('submit'));
    };

    function hideResults() {
        resultsContainer.classList.add('hidden');
        if (exportBar) exportBar.classList.add('hidden');
        document.getElementById('profileCard').classList.add('hidden');
        document.getElementById('summaryBar').classList.add('hidden');
        document.getElementById('aiSummaryCard').classList.add('hidden');
        document.getElementById('timelineCard').classList.add('hidden');
        document.getElementById('breachCard').classList.add('hidden');
        document.getElementById('githubCard').classList.add('hidden');
        document.getElementById('whoisCard').classList.add('hidden');
        document.getElementById('dnsCard').classList.add('hidden');
        document.getElementById('sslCard').classList.add('hidden');
        document.getElementById('vtCard').classList.add('hidden');
        document.getElementById('usernameCard').classList.add('hidden');
        document.getElementById('phoneCard').classList.add('hidden');
        document.getElementById('googleCard').classList.add('hidden');
        document.getElementById('ipCard').classList.add('hidden');
        document.getElementById('riskCard').classList.add('hidden');
        document.getElementById('relationshipsCard').classList.add('hidden');
        document.getElementById('metaBar').classList.add('hidden');
    }

    // Global expand/collapse toggle for expandable sections
    window.toggleExpand = function(id, btn) {
        const items = document.querySelectorAll(`[data-expandable="${id}"]`);
        const isHidden = items.length > 0 && items[0].style.display === 'none';
        if (btn && !btn.dataset.originalText) {
            btn.dataset.originalText = btn.textContent;
        }
        items.forEach(el => {
            if (isHidden) {
                // Restore intended display (flex for wrapped containers, block for others)
                el.style.display = el.dataset.expandDisplay || '';
            } else {
                el.style.display = 'none';
            }
        });
        if (btn) {
            btn.textContent = isHidden ? 'Show less' : btn.dataset.originalText;
        }
    };

    // ============ Replay: Load previous search from URL param ============
    (async function checkReplay() {
        const params = new URLSearchParams(window.location.search);
        const replayId = params.get('replay');
        if (!replayId) return;

        setLoading(true);
        try {
            const res = await fetch('/vignette/api/index.php?route=replay&id=' + encodeURIComponent(replayId));
            const data = await res.json();
            if (data.success && data.profile) {
                // Set the search input to match
                searchInput.value = data.query_value || '';
                const typeBtn = document.querySelector('.type-btn[data-type="' + (data.query_type || '') + '"]');
                if (typeBtn) {
                    typeButtons.forEach(b => b.classList.remove('active'));
                    typeBtn.classList.add('active');
                    currentType = data.query_type;
                }
                renderResults(data);
            }
        } catch (e) {
            // Silently fail — user can just search normally
        } finally {
            setLoading(false);
            // Clean up URL
            window.history.replaceState({}, '', window.location.pathname);
        }
    })();
})();
