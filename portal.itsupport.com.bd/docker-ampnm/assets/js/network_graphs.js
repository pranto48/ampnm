function initNetworkGraphs() {
    const graphCards = document.querySelectorAll('.graph-card');

    graphCards.forEach((card) => {
        const frame = card.querySelector('.graph-frame');
        const rangePill = card.querySelector('.range-pill');
        const openLink = card.querySelector('.graph-open-link');
        const buttons = card.querySelectorAll('.range-button');
        const currentInEl = card.querySelector('[data-current-in]');
        const currentOutEl = card.querySelector('[data-current-out]');
        const refreshBtn = card.querySelector('.refresh-graph-stats');
        const statusEl = card.querySelector('.graph-stats-status');

        async function fetchLiveStats(targetUrl) {
            if (!targetUrl || !currentInEl || !currentOutEl) return;

            if (statusEl) {
                statusEl.textContent = 'Loading live totals...';
            }

            try {
                const response = await fetch('fetch_graph_stats.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url: targetUrl }),
                });

                if (response.status === 204) {
                    currentInEl.textContent = '—';
                    currentOutEl.textContent = '—';
                    if (statusEl) statusEl.textContent = 'No live totals reported';
                    return;
                }

                if (!response.ok) {
                    throw new Error('Unable to pull stats');
                }

                const payload = await response.json();
                const inValue = payload.current_in || '—';
                const outValue = payload.current_out || '—';

                currentInEl.textContent = inValue;
                currentOutEl.textContent = outValue;
                if (statusEl) statusEl.textContent = 'Current In/Out refreshed';
            } catch (error) {
                console.error('Failed to update graph stats', error);
                currentInEl.textContent = '—';
                currentOutEl.textContent = '—';
                if (statusEl) statusEl.textContent = 'Live totals unavailable';
            }
        }

        function setRange(range) {
            const datasetKey = `${range}Url`;
            const targetUrl = card.dataset[datasetKey];

            if (!targetUrl) return;

            if (frame) {
                frame.src = targetUrl;
            }

            if (openLink) {
                openLink.href = targetUrl;
            }

            if (rangePill) {
                rangePill.textContent = range.charAt(0).toUpperCase() + range.slice(1);
            }

            buttons.forEach((btn) => {
                if (btn.dataset.range === range) {
                    btn.classList.add('bg-slate-700', 'text-white');
                } else {
                    btn.classList.remove('bg-slate-700', 'text-white');
                }
            });

            fetchLiveStats(targetUrl);
        }

        buttons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const range = button.dataset.range;
                setRange(range);
            });
        });

        if (refreshBtn) {
            refreshBtn.addEventListener('click', (event) => {
                event.preventDefault();
                const activeRange = card.querySelector('.range-button.bg-slate-700')?.dataset.range || 'daily';
                const datasetKey = `${activeRange}Url`;
                fetchLiveStats(card.dataset[datasetKey]);
            });
        }

        // Initialize with daily view
        setRange('daily');
    });
}
