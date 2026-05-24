import { getJson, request } from '../lib/http';
import { closeActiveModal, openFormModal, openMessageModal } from '../lib/modal';
import { subscribeToChannel } from '../lib/realtime';
import { showToast } from '../lib/toast';
import { badgeClass, escapeHtml, parseJsonScript, statusLabel } from '../lib/utils';

let counterClockInterval = null;

function requiredLabel(label) {
    return `${label}<span class="field-required">*</span>`;
}

export function initCounterPage() {
    const root = document.getElementById('counter-root');

    if (!root) {
        return;
    }

    let snapshot = parseJsonScript('counter-payload');

    render(snapshot);
    startRealtime();

    root.addEventListener('click', async (event) => {
        const action = event.target.closest('[data-action]')?.dataset.action;

        if (!action) {
            return;
        }

        if (action === 'open-context') {
            openContextModal(snapshot, root.dataset.contextUrl, async () => {
                snapshot = await getJson(root.dataset.snapshotUrl);
                render(snapshot);
            });

            return;
        }

        const actionMap = {
            call: root.dataset.callNextUrl,
            recall: root.dataset.recallUrl,
            start: root.dataset.startServingUrl,
            complete: root.dataset.completeUrl,
            skip: root.dataset.skipUrl,
            cancel: root.dataset.cancelUrl,
        };

        const messageMap = {
            cancel: 'Batalkan tiket yang sedang aktif?',
        };

        if (messageMap[action]) {
            openMessageModal(
                'Konfirmasi Aksi',
                `<p class="text-sm text-stone-700">${messageMap[action]}</p>`,
                `
                    <div class="flex flex-wrap justify-end gap-3">
                        <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
                        <button type="button" class="btn ${action === 'cancel' ? 'btn-danger' : 'btn-primary'}" data-confirm-action="${action}">
                            Lanjutkan
                        </button>
                    </div>
                `,
            );

            document.querySelector('[data-confirm-action]')?.addEventListener('click', async () => {
                closeActiveModal();
                await runAction(actionMap[action]);
            }, { once: true });

            return;
        }

        await runAction(actionMap[action]);
    });

    async function runAction(url) {
        try {
            const response = await request('post', url);
            showToast(response.message, 'success');
            await refreshSnapshot();
        } catch (error) {
            showToast(extractErrorMessage(error), 'error');
        }
    }

    async function refreshSnapshot() {
        snapshot = await getJson(root.dataset.snapshotUrl);
        render(snapshot);
    }

    function startRealtime() {
        subscribeToChannel({
            channelName: root.dataset.channel,
            eventName: 'queue.display.updated',
            onMessage: () => refreshSnapshot().catch(() => {}),
            onFallback: () => refreshSnapshot().catch(() => {}),
        });
    }
}

function render(snapshot) {
    const root = document.getElementById('counter-root');
    const current = snapshot.current_ticket;
    const hasContext = Boolean(snapshot.selected_counter_id && snapshot.selected_service_id);
    const hasCounters = snapshot.counters.length > 0;

    root.innerHTML = `
        <section class="grid gap-4 md:grid-cols-3">
            ${renderStats(snapshot.stats)}
        </section>

        ${hasContext ? `
            <section class="panel">
                <div class="panel-header flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="section-title">Counter Aktif</h2>
                        <div class="mt-2 flex flex-wrap items-center gap-y-1 text-sm text-stone-600">
                            <span><span class="text-stone-500">Counter:</span> ${escapeHtml(currentSelection(snapshot, 'counter'))}</span>
                            <span class="mx-2 inline-block h-1.5 w-1.5 rounded-full bg-stone-400"></span>
                            <span><span class="text-stone-500">Layanan:</span> ${escapeHtml(currentSelection(snapshot, 'service'))}</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary" data-action="open-context">
                        <i class="fa-solid fa-sliders"></i>
                        Ubah Counter & Layanan
                    </button>
                </div>
                <div class="panel-body">
                    <div class="rounded-md border border-stone-200 bg-amber-50 px-4 py-5">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div class="min-w-0">
                                ${current ? `
                                <p class="text-sm uppercase tracking-[0.18em] text-stone-500">Tiket Saat Ini</p>
                                <p class="counter-queue mt-2">${escapeHtml(current.queue_number)}</p>
                                <p class="mt-1 text-sm text-stone-700">${renderCurrentTicketMeta(current)}</p>
                                ` : `
                                <p class="text-sm text-stone-700">Belum ada tiket aktif di counter ini.</p>
                                <p class="mt-2 text-sm text-stone-600">Gunakan tombol panggil untuk mengambil tiket berikutnya dari antrian.</p>
                                `}
                            </div>
                            <div class="grid gap-2 md:w-[480px] md:flex-none md:grid-cols-[1fr_140px]">
                                <div class="grid gap-2 md:grid-cols-2">
                                    <button type="button" class="btn btn-secondary w-full py-1.5" data-action="recall" ${disableIf(!current || current.status !== 'called')}>
                                        <i class="fa-solid fa-volume-high"></i>
                                        Panggil Ulang
                                    </button>
                                    <button type="button" class="btn btn-info w-full py-1.5" data-action="start" ${disableIf(!current || current.status !== 'called')}>
                                        <i class="fa-solid fa-play"></i>
                                        Mulai Layani
                                    </button>
                                    <button type="button" class="btn btn-success w-full py-1.5" data-action="complete" ${disableIf(!current || current.status !== 'serving')}>
                                        <i class="fa-solid fa-check"></i>
                                        Selesaikan
                                    </button>
                                    <button type="button" class="btn btn-warning w-full py-1.5" data-action="skip" ${disableIf(!current || current.status !== 'called')}>
                                        <i class="fa-solid fa-forward"></i>
                                        Lewati
                                    </button>
                                </div>
                                <button type="button" class="btn btn-primary h-full min-h-[88px] w-full flex-col gap-2 py-2" data-action="call" ${disableIf(current && ['called', 'serving'].includes(current.status))}>
                                    <i class="fa-solid fa-bullhorn"></i>
                                    Panggil Berikutnya
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header flex items-center justify-between gap-3">
                    <h2 class="section-title">Tiket Menunggu Berikutnya</h2>
                    <span class="badge-live">
                        <span class="live-dot" aria-hidden="true"></span>
                        LIVE
                    </span>
                </div>
                <div class="panel-body">
                    ${snapshot.next_tickets.length > 0 ? `
                        <div class="flex gap-3 overflow-x-auto pb-1">
                            ${snapshot.next_tickets.map((ticket) => `
                                <article class="stat-card min-w-[220px] flex-none shadow-none">
                                    <p class="text-sm uppercase tracking-[0.18em] text-stone-500">Tiket</p>
                                    <p class="mt-2 text-2xl font-semibold">${escapeHtml(ticket.queue_number)}</p>
                                    <p class="mt-1 text-sm text-stone-400">${escapeHtml(formatTicketCreatedAt(ticket.created_at))}</p>
                                </article>
                            `).join('')}
                        </div>
                    ` : `
                        <div class="empty-state">Belum ada tiket menunggu untuk layanan yang dipilih.</div>
                    `}
                </div>
            </section>
        ` : `
            <section class="panel">
                <div class="panel-body">
                    <div class="empty-state space-y-4">
                        <div>
                            <p class="text-base font-medium text-stone-900">${hasCounters ? 'Pilih counter dan layanan terlebih dahulu.' : 'Belum ada counter yang ditugaskan.'}</p>
                            <p class="mt-1 text-sm text-stone-600">${hasCounters ? 'Setelah dipilih, detail counter aktif dan daftar tiket menunggu akan tampil di sini.' : 'Hubungi admin tenant untuk menugaskan Anda ke counter tertentu.'}</p>
                        </div>
                        ${hasCounters ? `
                            <button type="button" class="btn btn-primary" data-action="open-context">
                                <i class="fa-solid fa-sliders"></i>
                                Pilih Counter & Layanan
                            </button>
                        ` : ''}
                    </div>
                </div>
            </section>
        `}
    `;

    startCounterClock();
}

function renderStats(stats) {
    const statCards = Object.entries({
        waiting: 'Menunggu',
        completed: 'Selesai',
    }).map(([key, label]) => `
        <article class="stat-card">
            <p class="text-sm uppercase tracking-[0.18em] text-stone-500">${label}</p>
            <p class="mt-2 text-3xl font-semibold">${stats[key]}</p>
        </article>
    `).join('');

    return `${statCards}
        <article class="stat-card">
            <p class="text-sm uppercase tracking-[0.18em] text-stone-500" data-counter-live-date>--</p>
            <p class="mt-2 text-3xl font-semibold" data-counter-live-clock>--:--:--</p>
        </article>
    `;
}

function openContextModal(snapshot, url, onSaved) {
    if (snapshot.counters.length === 0) {
        openMessageModal(
            'Counter Belum Tersedia',
            '<p class="text-sm text-stone-700">Belum ada counter yang ditugaskan ke akun Anda.</p>',
        );

        return;
    }

    openFormModal(
        'Pilih Counter dan Layanan',
        `
            <form id="counter-context-form" class="grid gap-4">
                <div>
                    <label class="field-label">${requiredLabel('Counter')}</label>
                    <select name="counter_id" class="field" required>
                        <option value="">Pilih counter</option>
                        ${snapshot.counters.map((counter) => `
                            <option value="${counter.id}" ${counter.id === snapshot.selected_counter_id ? 'selected' : ''}>
                                ${escapeHtml(counter.name)}${counter.is_active ? '' : ' (nonaktif)'}
                            </option>
                        `).join('')}
                    </select>
                </div>
                <div>
                    <label class="field-label">${requiredLabel('Layanan')}</label>
                    <select name="service_id" class="field" required>
                        <option value="">Pilih layanan</option>
                        ${snapshot.services.map((service) => `
                            <option value="${service.id}" ${service.id === snapshot.selected_service_id ? 'selected' : ''}>
                                ${escapeHtml(service.name)}
                            </option>
                        `).join('')}
                    </select>
                </div>
            </form>
        `,
        `
            <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
            <button type="submit" form="counter-context-form" class="btn btn-primary">Simpan Pilihan</button>
        `,
    );

    document.getElementById('counter-context-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);

        try {
            const response = await request('post', url, Object.fromEntries(form.entries()));
            showToast(response.message, 'success');
            closeActiveModal();
            await onSaved();
        } catch (error) {
            showToast(extractErrorMessage(error), 'error');
        }
    }, { once: true });
}

function currentSelection(snapshot, type) {
    if (type === 'counter') {
        return snapshot.counters.find((item) => item.id === snapshot.selected_counter_id)?.name ?? 'Belum dipilih';
    }

    return snapshot.services.find((item) => item.id === snapshot.selected_service_id)?.name ?? 'Belum dipilih';
}

function disableIf(condition) {
    return condition ? 'disabled' : '';
}

function startCounterClock() {
    const clockElement = document.querySelector('[data-counter-live-clock]');
    const dateElement = document.querySelector('[data-counter-live-date]');
    const servingElapsedElement = document.querySelector('[data-counter-serving-elapsed]');

    if (counterClockInterval) {
        window.clearInterval(counterClockInterval);
        counterClockInterval = null;
    }

    if (!clockElement || !dateElement) {
        return;
    }

    const update = () => {
        const now = new Date();

        clockElement.textContent = formatTicketCreatedAt(now.toISOString());
        dateElement.textContent = new Intl.DateTimeFormat('id-ID', {
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric',
        }).format(now);

        if (servingElapsedElement) {
            servingElapsedElement.textContent = formatElapsedTime(servingElapsedElement.dataset.servingStartedAt, now);
        }
    };

    update();
    counterClockInterval = window.setInterval(update, 1000);
}

function formatTicketCreatedAt(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');

    return `${hours}:${minutes}:${seconds}`;
}

function renderCurrentTicketMeta(ticket) {
    const timestamp = ticket.status === 'serving'
        ? ticket.serving_started_at ?? ticket.called_at ?? ticket.created_at
        : ticket.called_at ?? ticket.created_at;

    const parts = [
        escapeHtml(formatTicketCreatedAt(timestamp)),
        `<span class="${currentTicketStatusClass(ticket.status)}">${escapeHtml(statusLabel(ticket.status))}</span>`,
    ];

    if (ticket.status === 'serving' && ticket.serving_started_at) {
        parts.push(`<span class="font-medium text-stone-700" data-counter-serving-elapsed data-serving-started-at="${escapeHtml(ticket.serving_started_at)}">${escapeHtml(formatElapsedTime(ticket.serving_started_at))}</span>`);
    }

    return parts.join(' <span class="text-stone-400">&bull;</span> ');
}

function currentTicketStatusClass(status) {
    const classMap = {
        waiting: 'font-semibold text-stone-500',
        called: 'font-semibold text-amber-700',
        serving: 'font-semibold text-sky-700',
        completed: 'font-semibold text-emerald-700',
        skipped: 'font-semibold text-orange-700',
        cancelled: 'font-semibold text-rose-700',
    };

    return classMap[status] ?? 'font-semibold text-stone-700';
}

function formatElapsedTime(startedAt, now = new Date()) {
    if (!startedAt) {
        return '00:00';
    }

    const start = new Date(startedAt);
    const diffMs = Math.max(0, now.getTime() - start.getTime());
    const totalSeconds = Math.floor(diffMs / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
    const seconds = String(totalSeconds % 60).padStart(2, '0');

    if (hours > 0) {
        return `${String(hours).padStart(2, '0')}:${minutes}:${seconds}`;
    }

    return `${minutes}:${seconds}`;
}

function extractErrorMessage(error) {
    const firstValidationMessage = error?.response?.data?.errors
        ? Object.values(error.response.data.errors)[0]?.[0]
        : null;

    return firstValidationMessage ?? error?.response?.data?.message ?? error?.message ?? 'Permintaan gagal.';
}
