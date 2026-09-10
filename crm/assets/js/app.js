/* =============================================
   GREEN WASH CRM – app.js
   ============================================= */

// ── SIDEBAR TOGGLE ────────────────────────
const sidebar    = document.getElementById('sidebar');
const menuToggle = document.getElementById('menuToggle');
const overlay    = document.getElementById('overlay');
const sideClose  = document.getElementById('sidebarClose');

function openSidebar() {
    sidebar?.classList.add('open');
    overlay?.classList.add('visible');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('visible');
    document.body.style.overflow = '';
}

menuToggle?.addEventListener('click', openSidebar);
overlay?.addEventListener('click', closeSidebar);
sideClose?.addEventListener('click', closeSidebar);

// ── FILTER PANEL ─────────────────────────
document.querySelectorAll('[data-toggle-filter]').forEach(btn => {
    btn.addEventListener('click', () => {
        const panel = document.getElementById('filterPanel');
        panel?.classList.toggle('open');
    });
});
document.getElementById('clearFilters')?.addEventListener('click', () => {
    document.querySelectorAll('#filterPanel select, #filterPanel input').forEach(el => el.value = '');
    document.getElementById('searchForm')?.submit();
});

// ── MODALES ───────────────────────────────
function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('open');
    document.body.style.overflow = '';
}

document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => closeModal(btn.dataset.modalClose));
});
document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', e => {
        if (e.target === backdrop) {
            backdrop.classList.remove('open');
            document.body.style.overflow = '';
        }
    });
});

// ── EXP CARD CLICK → MODAL ───────────────
document.querySelectorAll('.exp-card[data-id]').forEach(card => {
    card.addEventListener('click', (e) => {
        if (e.target.closest('button, select, input, a, .clasif-panel, .clasif-badge, .doc-send-section, .fase-change-section, .anular-section')) return;
        const id = card.dataset.id;
        const modal = document.getElementById('modal-exp-' + id);
        if (modal) openModal('modal-exp-' + id);
    });
});

// ── TABS ─────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const group = btn.dataset.tabGroup;
        document.querySelectorAll(`.tab-btn[data-tab-group="${group}"]`).forEach(b => b.classList.remove('active'));
        document.querySelectorAll(`.tab-content[data-tab-group="${group}"]`).forEach(c => c.classList.add('hidden'));
        btn.classList.add('active');
        const target = document.getElementById(btn.dataset.target);
        target?.classList.remove('hidden');
    });
});

// ── BÚSQUEDA — solo al pulsar Enter o el botón ───────────────
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('searchForm')?.submit();
        }
    });
}

function debounce(fn, delay) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
}

// ── LOAD MORE (AJAX) ──────────────────────
const loadMoreBtn = document.getElementById('loadMoreBtn');
if (loadMoreBtn) {
    loadMoreBtn.addEventListener('click', () => {
        const offset   = parseInt(loadMoreBtn.dataset.offset || '0');
        const endpoint = loadMoreBtn.dataset.endpoint;
        const params   = new URLSearchParams(loadMoreBtn.dataset.params || '');
        params.set('offset', offset);

        loadMoreBtn.disabled = true;
        loadMoreBtn.innerHTML = '<span class="spinner"></span> Cargando...';

        fetch(endpoint + '?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('expList');
            if (data.html) {
                list.insertAdjacentHTML('beforeend', data.html);
                list.querySelectorAll('.exp-card[data-id]:not([data-bound])').forEach(card => {
                    card.dataset.bound = '1';
                    card.addEventListener('click', () => openModal('modal-exp-' + card.dataset.id));
                });
                if (data.modals) document.body.insertAdjacentHTML('beforeend', data.modals);
            }
            loadMoreBtn.dataset.offset = offset + data.count;
            if (!data.hasMore) loadMoreBtn.remove();
            else {
                loadMoreBtn.disabled = false;
                loadMoreBtn.innerHTML = '⬇️ Cargar más expedientes';
            }
        })
        .catch(() => {
            loadMoreBtn.disabled = false;
            loadMoreBtn.textContent = 'Error. Reintentar';
        });
    });
}

// ── ESC KEY ──────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeSidebar();
        document.querySelectorAll('.modal-backdrop.open').forEach(m => {
            m.classList.remove('open');
            setTimeout(() => m.style.display = 'none', 220);
        });
        document.body.style.overflow = '';
    }
});
