import { getJson, request } from '../lib/http';
import { closeActiveModal, openFormModal, openMessageModal } from '../lib/modal';
import { showToast } from '../lib/toast';
import { badgeClass, dayLabel, escapeHtml, parseJsonScript, statusLabel } from '../lib/utils';

function requiredLabel(label) {
    return `${label}<span class="field-required">*</span>`;
}

function tableHeader(icon, label) {
    return `
        <span class="inline-flex items-center gap-2">
            <i class="fa-solid fa-${icon} text-stone-500"></i>
            <span>${label}</span>
        </span>
    `;
}

function renderStatusBadge(ticket) {
    return `
        <span class="${badgeClass(ticket.status)}">
            ${escapeHtml(statusLabel(ticket.status))}
            ${renderCompletedDuration(ticket)}
        </span>
    `;
}

function renderCompletedDuration(ticket) {
    const label = formatCompletedDuration(ticket);

    if (!label) {
        return '';
    }

    return `&nbsp;<span class="font-normal">${escapeHtml(label)}</span>`;
}

function formatCompletedDuration(ticket) {
    if (ticket.status !== 'completed' || !ticket.serving_started_at || !ticket.completed_at) {
        return null;
    }

    const startedAt = new Date(ticket.serving_started_at);
    const completedAt = new Date(ticket.completed_at);
    const diffMs = Math.max(0, completedAt.getTime() - startedAt.getTime());
    const durationMinutes = Math.max(1, Math.floor(diffMs / 60000));

    return `dalam ${durationMinutes} menit`;
}

export function initAdminPage() {
    const root = document.getElementById('admin-root');

    if (!root) {
        return;
    }

    let snapshot = parseJsonScript('admin-payload');
    const section = root.dataset.section;
    const query = new URLSearchParams(window.location.search);

    render();

    root.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');

        if (!button) {
            return;
        }

        const action = button.dataset.action;

        if (action === 'page-change') {
            updatePageQuery(button.dataset.pageTarget, Number(button.dataset.page));
            await refresh();
            return;
        }

        const handlers = {
            'service-create': () => openServiceModal(),
            'service-edit': () => openServiceModal(findService(button.dataset.id)),
            'service-delete': () => confirmDelete(`${root.dataset.serviceUrlBase}/${button.dataset.id}`, 'Layanan berhasil dihapus.'),
            'schedule-create': () => openScheduleModal(button.dataset.serviceId),
            'schedule-edit': () => openScheduleModal(button.dataset.serviceId, button.dataset.scheduleId),
            'schedule-duplicate': () => openScheduleModal(button.dataset.serviceId, button.dataset.scheduleId, true),
            'schedule-delete': () => confirmDelete(`${root.dataset.scheduleUrlBase}/${button.dataset.scheduleId}`, 'Jadwal layanan berhasil dihapus.'),
            'counter-create': () => openCounterModal(),
            'counter-edit': () => openCounterModal(findCounter(button.dataset.id)),
            'counter-delete': () => confirmDelete(`${root.dataset.counterUrlBase}/${button.dataset.id}`, 'Counter berhasil dihapus.'),
            'user-create': () => openUserModal(),
            'user-delete': () => confirmDelete(`${root.dataset.userUrlBase}/${button.dataset.id}`, 'Akses pengguna berhasil dihapus.'),
        };

        await handlers[action]?.();
    });

    root.addEventListener('submit', async (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || form.id !== 'tenant-settings-form') {
            return;
        }

        event.preventDefault();

        try {
            const response = await request('put', root.dataset.settingsUrl, Object.fromEntries(new FormData(form).entries()));
            showToast(response.message, 'success');
            await refresh();
        } catch (error) {
            showToast(extractErrorMessage(error), 'error');
        }
    });

    async function refresh() {
        snapshot = await getJson(buildSnapshotUrl());
        render();
    }

    function render() {
        syncQueryToSnapshot();
        root.innerHTML = renderSectionContent();
    }

    function renderSectionContent() {
        if (section === 'overview') {
            return `
                <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    ${renderStats(snapshot.stats)}
                </section>
                <section class="panel border-stone-200">
                    <div class="panel-header">
                        <h2 class="section-title">Ringkasan Hari Ini</h2>
                    </div>
                    <div class="panel-body">
                        <div class="table-wrap rounded-none border-0">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>${tableHeader('ticket-simple', 'Nomor')}</th>
                                        <th>${tableHeader('bell-concierge', 'Layanan')}</th>
                                        <th>${tableHeader('circle-info', 'Status')}</th>
                                        <th>${tableHeader('headset', 'Counter')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${snapshot.recent_tickets.length > 0 ? snapshot.recent_tickets.map((ticket) => `
                                        <tr>
                                            <td class="font-semibold">${escapeHtml(ticket.queue_number)}</td>
                                            <td>${escapeHtml(ticket.service.name)}</td>
                                            <td>
                                                ${renderStatusBadge(ticket)}
                                            </td>
                                            <td>${escapeHtml(ticket.counter?.name ?? '-')}</td>
                                        </tr>
                                    `).join('') : `
                                        <tr>
                                            <td colspan="4"><div class="empty-state">Belum ada tiket hari ini.</div></td>
                                        </tr>
                                    `}
                                </tbody>
                            </table>
                            ${renderPagination(snapshot.recent_tickets_pagination, 'tickets_page')}
                        </div>
                    </div>
                </section>
            `;
        }

        if (section === 'services') {
            return `
                <div class="space-y-6">
                    <div class="flex flex-wrap justify-end gap-3">
                        <button type="button" class="btn btn-primary" data-action="service-create">
                            <i class="fa-solid fa-plus"></i>
                            Tambah Layanan
                        </button>
                    </div>
                    <section class="panel border-stone-200">
                        <div class="panel-body">
                            <div class="table-wrap rounded-none border-0">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>${tableHeader('bell-concierge', 'Layanan')}</th>
                                        <th>${tableHeader('calendar-days', 'Jadwal')}</th>
                                        <th class="w-px whitespace-nowrap">${tableHeader('screwdriver-wrench', 'Aksi')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${snapshot.services.length > 0 ? snapshot.services.map((service) => `
                                        <tr>
                                            <td class="font-medium">${escapeHtml(service.name)}</td>
                                            <td>${service.schedules_count}</td>
                                            <td class="whitespace-nowrap">
                                                <div class="table-actions">
                                                    <a href="${root.dataset.serviceSchedulesPageBase}/${service.id}/jadwal" class="btn btn-secondary">
                                                        <i class="fa-solid fa-calendar-days"></i>
                                                        Jadwal
                                                    </a>
                                                    <button type="button" class="btn btn-secondary px-3 py-2" data-action="service-edit" data-id="${service.id}">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-danger px-3 py-2" data-action="service-delete" data-id="${service.id}">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    `).join('') : `
                                        <tr>
                                            <td colspan="3"><div class="empty-state">Belum ada layanan.</div></td>
                                        </tr>
                                    `}
                                </tbody>
                            </table>
                            ${renderPagination(snapshot.services_pagination, 'services_page')}
                            </div>
                        </div>
                    </section>
                </div>
            `;
        }

        if (section === 'service-schedules') {
            return `
                <div class="space-y-6">
                    <section class="panel border-stone-200">
                        <div class="panel-header flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="section-title">${escapeHtml(snapshot.service.name)}</h2>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a href="${root.dataset.serviceSchedulesPageBase}" class="btn btn-secondary">
                                    <i class="fa-solid fa-arrow-left"></i>
                                    Kembali ke Layanan
                                </a>
                                <button type="button" class="btn btn-primary" data-action="schedule-create" data-service-id="${snapshot.service.id}">
                                    <i class="fa-solid fa-calendar-plus"></i>
                                    Tambah Jadwal
                                </button>
                            </div>
                        </div>
                        <div class="panel-body table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>${tableHeader('calendar-day', 'Hari')}</th>
                                        <th class="w-px whitespace-nowrap">${tableHeader('clock', 'Jam')}</th>
                                        <th class="w-px whitespace-nowrap">${tableHeader('hourglass-start', 'Pra-Antrian')}</th>
                                        <th class="w-px whitespace-nowrap">${tableHeader('users', 'Kuota')}</th>
                                        <th class="w-px whitespace-nowrap">${tableHeader('circle-info', 'Status')}</th>
                                        <th class="w-px whitespace-nowrap">${tableHeader('screwdriver-wrench', 'Aksi')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${snapshot.schedules.length > 0 ? snapshot.schedules.map((schedule) => `
                                        <tr>
                                            <td>${dayLabel(schedule.day)}</td>
                                            <td class="whitespace-nowrap">${escapeHtml(schedule.opens_at ?? '-')} - ${escapeHtml(schedule.closes_at ?? '-')}</td>
                                            <td class="whitespace-nowrap">${formatPreQueueMinutes(schedule.pre_queue_minutes)}</td>
                                            <td class="whitespace-nowrap">${schedule.max_tickets ?? 'Tanpa batas'}</td>
                                            <td class="whitespace-nowrap"><span class="${schedule.is_available ? 'badge badge-serving' : 'badge badge-cancelled'}">${schedule.is_available ? 'Tersedia' : 'Tidak tersedia'}</span></td>
                                            <td class="whitespace-nowrap">
                                                <div class="table-actions">
                                                    <button type="button" class="btn btn-secondary px-3 py-2" data-action="schedule-duplicate" data-service-id="${snapshot.service.id}" data-schedule-id="${schedule.id}">
                                                        <i class="fa-solid fa-copy"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-secondary px-3 py-2" data-action="schedule-edit" data-service-id="${snapshot.service.id}" data-schedule-id="${schedule.id}">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-danger px-3 py-2" data-action="schedule-delete" data-schedule-id="${schedule.id}">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    `).join('') : `
                                        <tr>
                                            <td colspan="6"><div class="empty-state">Belum ada jadwal untuk layanan ini.</div></td>
                                        </tr>
                                    `}
                                </tbody>
                            </table>
                            ${renderPagination(snapshot.schedules_pagination, 'schedules_page')}
                        </div>
                    </section>
                </div>
            `;
        }

        if (section === 'counters') {
            return `
                <div class="space-y-5">
                    <div class="flex flex-wrap justify-end gap-3">
                        <button type="button" class="btn btn-primary" data-action="counter-create">
                            <i class="fa-solid fa-plus"></i>
                            Tambah Counter
                        </button>
                    </div>
                    <section class="panel border-stone-200">
                        <div class="panel-body">
                            <div class="table-wrap rounded-none border-0">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>${tableHeader('headset', 'Counter')}</th>
                                        <th class="w-px whitespace-nowrap">${tableHeader('circle-info', 'Status')}</th>
                                        <th class="w-px whitespace-nowrap">${tableHeader('screwdriver-wrench', 'Aksi')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${snapshot.counters.length > 0 ? snapshot.counters.map((counter) => `
                                        <tr>
                                            <td class="font-medium">${escapeHtml(counter.name)}</td>
                                            <td class="whitespace-nowrap"><span class="${counter.is_active ? 'badge badge-serving' : 'badge badge-cancelled'}">${counter.is_active ? 'Aktif' : 'Nonaktif'}</span></td>
                                            <td class="whitespace-nowrap">
                                                <div class="table-actions">
                                                    <button type="button" class="btn btn-secondary px-3 py-2" data-action="counter-edit" data-id="${counter.id}">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-danger px-3 py-2" data-action="counter-delete" data-id="${counter.id}">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    `).join('') : `
                                        <tr>
                                            <td colspan="3"><div class="empty-state">Belum ada counter.</div></td>
                                        </tr>
                                    `}
                                </tbody>
                            </table>
                            ${renderPagination(snapshot.counters_pagination, 'counters_page')}
                            </div>
                        </div>
                    </section>
                </div>
            `;
        }

        if (section === 'users') {
            return `
                <div class="space-y-5">
                    <div class="flex flex-wrap justify-end gap-3">
                        <button type="button" class="btn btn-primary" data-action="user-create">
                            <i class="fa-solid fa-user-plus"></i>
                            Tambah Akses
                        </button>
                    </div>
                    <section class="panel border-stone-200">
                        <div class="panel-body">
                            <div class="table-wrap rounded-none border-0">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>${tableHeader('user', 'Pengguna')}</th>
                                            <th>${tableHeader('envelope', 'Email')}</th>
                                            <th>${tableHeader('circle-info', 'Status')}</th>
                                            <th class="w-px whitespace-nowrap">${tableHeader('screwdriver-wrench', 'Aksi')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${snapshot.admins.length > 0 ? snapshot.admins.map((user) => `
                                            <tr>
                                                <td class="font-medium">${escapeHtml(user.name)}</td>
                                                <td>${escapeHtml(user.email)}</td>
                                                <td><span class="${user.is_owner ? 'badge badge-called' : 'badge badge-serving'}">${user.is_owner ? 'Pemilik' : user.role === 'admin' ? 'Admin' : 'Petugas'}</span></td>
                                                <td class="whitespace-nowrap">
                                                    ${user.is_owner ? '<span class="muted">Tetap</span>' : `
                                                        <div class="table-actions">
                                                            <button type="button" class="btn btn-danger px-3 py-2" data-action="user-delete" data-id="${user.id}">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    `}
                                                </td>
                                            </tr>
                                        `).join('') : `
                                            <tr>
                                                <td colspan="4"><div class="empty-state">Belum ada akses tambahan.</div></td>
                                            </tr>
                                        `}
                                    </tbody>
                                </table>
                                ${renderPagination(snapshot.admins_pagination, 'users_page')}
                            </div>
                        </div>
                    </section>
                </div>
            `;
        }

        return `
            <div class="space-y-6">
                <section class="panel border-stone-200">
                    <div class="panel-header">
                        <h2 class="section-title">Pengaturan Tenant</h2>
                    </div>
                    <form id="tenant-settings-form" class="panel-body grid gap-4">
                        <div>
                            <label class="field-label">${requiredLabel('Nama Tenant')}</label>
                            <input name="name" class="field" value="${escapeHtml(snapshot.tenant.name)}" required>
                        </div>
                        <div>
                            <label class="field-label">${requiredLabel('Kode Tenant')}</label>
                            <input name="code" class="field" value="${escapeHtml(snapshot.tenant.code)}" required>
                            <p class="field-help">Dipakai di URL publik tenant.</p>
                        </div>
                        <div>
                            <label class="field-label">${requiredLabel('Bahasa TTS')}</label>
                            <select name="tts_language" class="field" required>
                                <option value="id-ID" ${snapshot.tenant.tts_language === 'id-ID' ? 'selected' : ''}>Indonesia (id-ID)</option>
                                <option value="en-US" ${snapshot.tenant.tts_language === 'en-US' ? 'selected' : ''}>English (en-US)</option>
                            </select>
                        </div>
                        <div>
                            <label class="field-label">${requiredLabel('Template TTS')}</label>
                            <textarea name="tts_template" class="field min-h-28" required>${escapeHtml(snapshot.tenant.tts_template)}</textarea>
                            <p class="field-help">Gunakan <code>{queue}</code> dan <code>{counter}</code> sebagai variabel.</p>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Simpan
                            </button>
                        </div>
                    </form>
                </section>
                ${snapshot.permissions?.can_delete_tenant ? `
                    <section class="panel border-stone-200">
                        <div class="panel-header">
                            <h2 class="section-title">Hapus Tenant</h2>
                        </div>
                        <div class="panel-body flex flex-wrap items-center justify-between gap-4">
                            <p class="muted">Semua layanan, jadwal, counter, akses, dan tiket tenant ini akan ikut terhapus.</p>
                            <button
                                type="button"
                                class="btn btn-danger"
                                data-confirm-delete-url="${root.dataset.tenantDeleteUrl}"
                                data-confirm-delete-message="Tenant beserta seluruh data turunannya akan dihapus permanen. Lanjutkan?"
                            >
                                <i class="fa-solid fa-trash"></i>
                                Hapus Tenant
                            </button>
                        </div>
                    </section>
                ` : ''}
            </div>
        `;
    }

    function renderStats(stats) {
        return Object.entries({
            waiting: 'Menunggu',
            called: 'Dipanggil',
            serving: 'Dilayani',
            completed: 'Selesai',
            skipped: 'Dilewati',
            cancelled: 'Dibatalkan',
        }).map(([key, label]) => `
            <article class="stat-card">
                <p class="text-sm uppercase tracking-[0.18em] text-stone-500">${label}</p>
                <p class="mt-2 text-3xl font-semibold">${stats[key]}</p>
            </article>
        `).join('');
    }

    function renderPagination(pagination, pageTarget) {
        if (!pagination || !pagination.has_pages) {
            return '';
        }

        const pages = paginationRange(pagination.current_page, pagination.last_page);

        return `
            <div class="pagination-bar">
                <p class="pagination-summary">
                    Menampilkan ${pagination.from} - ${pagination.to} dari ${pagination.total}
                </p>
                <div class="pagination">
                    <button type="button" class="pagination-btn" data-action="page-change" data-page-target="${pageTarget}" data-page="${pagination.current_page - 1}" ${disableIf(pagination.current_page <= 1)}>
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                    ${pages.map((page) => page === '...'
                        ? `<span class="pagination-btn pointer-events-none">${page}</span>`
                        : `
                            <button
                                type="button"
                                class="pagination-btn ${page === pagination.current_page ? 'is-active' : ''}"
                                data-action="page-change"
                                data-page-target="${pageTarget}"
                                data-page="${page}"
                            >
                                ${page}
                            </button>
                        `
                    ).join('')}
                    <button type="button" class="pagination-btn" data-action="page-change" data-page-target="${pageTarget}" data-page="${pagination.current_page + 1}" ${disableIf(pagination.current_page >= pagination.last_page)}>
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        `;
    }

    function paginationRange(currentPage, lastPage) {
        if (lastPage <= 5) {
            return Array.from({ length: lastPage }, (_, index) => index + 1);
        }

        const pages = [1];
        const start = Math.max(2, currentPage - 1);
        const end = Math.min(lastPage - 1, currentPage + 1);

        if (start > 2) {
            pages.push('...');
        }

        for (let page = start; page <= end; page += 1) {
            pages.push(page);
        }

        if (end < lastPage - 1) {
            pages.push('...');
        }

        pages.push(lastPage);

        return pages;
    }

    function updatePageQuery(pageTarget, page) {
        if (!pageTarget || page < 1) {
            return;
        }

        query.set(pageTarget, String(page));
        window.history.replaceState({}, '', currentUrlWithQuery());
    }

    function buildSnapshotUrl() {
        const snapshotUrl = new URL(root.dataset.snapshotUrl, window.location.origin);
        snapshotUrl.search = query.toString();

        return snapshotUrl.toString();
    }

    function syncQueryToSnapshot() {
        const pageTargets = {
            overview: [['tickets_page', snapshot.recent_tickets_pagination]],
            services: [['services_page', snapshot.services_pagination]],
            'service-schedules': [['schedules_page', snapshot.schedules_pagination]],
            counters: [['counters_page', snapshot.counters_pagination]],
            users: [['users_page', snapshot.admins_pagination]],
        };

        for (const [target, pagination] of pageTargets[section] ?? []) {
            if (pagination?.current_page) {
                query.set(target, String(pagination.current_page));
            }
        }

        window.history.replaceState({}, '', currentUrlWithQuery());
    }

    function currentUrlWithQuery() {
        const search = query.toString();

        return search ? `${window.location.pathname}?${search}` : window.location.pathname;
    }

    function disableIf(condition) {
        return condition ? 'disabled' : '';
    }

    function findService(id) {
        if (section === 'service-schedules') {
            return String(snapshot.service?.id) === String(id) ? snapshot.service : null;
        }

        return snapshot.services.find((service) => String(service.id) === String(id));
    }

    function findCounter(id) {
        return snapshot.counters.find((counter) => String(counter.id) === String(id));
    }

    function findSchedule(serviceId, scheduleId) {
        if (section === 'service-schedules') {
            return snapshot.schedules.find((schedule) => String(schedule.id) === String(scheduleId));
        }

        return findService(serviceId)?.schedules.find((schedule) => String(schedule.id) === String(scheduleId));
    }

    function openServiceModal(service = null) {
        openFormModal(
            service ? 'Ubah Layanan' : 'Tambah Layanan',
            `
                <form id="service-form" class="grid gap-4">
                    <div>
                        <label class="field-label">${requiredLabel('Nama Layanan')}</label>
                        <input name="name" class="field" value="${escapeHtml(service?.name ?? '')}" required>
                    </div>
                    <div>
                        <label class="field-label">${requiredLabel('Prefix Tiket')}</label>
                        <input name="ticket_prefix" class="field" maxlength="10" value="${escapeHtml(service?.ticket_prefix ?? '')}" required>
                    </div>
                    <label class="switch-field">
                        <span>Wajib login pengguna</span>
                        <span class="flex items-center">
                            <input type="checkbox" name="is_login_required" value="1" ${service?.is_login_required ? 'checked' : ''} class="switch-input">
                            <span class="switch-control" aria-hidden="true"></span>
                        </span>
                    </label>
                </form>
            `,
            `
                <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
                <button type="submit" form="service-form" class="btn btn-primary">Simpan</button>
            `,
        );

        document.getElementById('service-form')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = new FormData(event.currentTarget);
            const payload = {
                name: form.get('name'),
                ticket_prefix: form.get('ticket_prefix'),
                is_login_required: form.get('is_login_required') ? 1 : 0,
            };

            await submitForm(service ? 'put' : 'post', service ? `${root.dataset.serviceUrlBase}/${service.id}` : root.dataset.servicesStoreUrl, payload);
        }, { once: true });
    }

    function openScheduleModal(serviceId, scheduleId = null, duplicate = false) {
        const schedule = scheduleId ? findSchedule(serviceId, scheduleId) : null;

        openFormModal(
            duplicate ? 'Duplikat Jadwal' : schedule ? 'Ubah Jadwal' : 'Tambah Jadwal',
            `
                <form id="schedule-form" class="grid gap-4">
                    <input type="hidden" name="service_id" value="${serviceId}">
                    <div>
                        <label class="field-label">${requiredLabel('Hari')}</label>
                        <select name="day" class="field" required>
                            ${[0, 1, 2, 3, 4, 5, 6].map((day) => `
                                <option value="${day}" ${Number(schedule?.day ?? 0) === day ? 'selected' : ''}>${dayLabel(day)}</option>
                            `).join('')}
                        </select>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="field-label">Buka</label>
                            <input name="opens_at" type="time" class="field" value="${escapeHtml(schedule?.opens_at ?? '')}">
                        </div>
                        <div>
                            <label class="field-label">Tutup</label>
                            <input name="closes_at" type="time" class="field" value="${escapeHtml(schedule?.closes_at ?? '')}">
                        </div>
                    </div>
                    <p class="field-help">Kosongkan jam buka dan tutup jika layanan tersedia sepanjang hari.</p>
                    <div>
                        <label class="field-label">Kuota Maksimum</label>
                        <input name="max_tickets" type="number" min="1" class="field" value="${escapeHtml(schedule?.max_tickets ?? '')}">
                        <p class="field-help">Kosongkan jika tidak ingin membatasi jumlah tiket.</p>
                    </div>
                    <div>
                        <label class="field-label">Pra-Antrian (menit)</label>
                        <input name="pre_queue_minutes" type="number" min="0" max="10080" class="field" value="${escapeHtml(schedule?.pre_queue_minutes ?? 0)}">
                        <p class="field-help">Isi jumlah menit sebelum jam buka agar tiket bisa diambil lebih awal. <code>1440</code> berarti 1 hari sebelum layanan dimulai.</p>
                    </div>
                    <label class="switch-field">
                        <span>Jadwal tersedia</span>
                        <span class="flex items-center">
                            <input type="checkbox" name="is_available" value="1" ${schedule?.is_available ?? true ? 'checked' : ''} class="switch-input">
                            <span class="switch-control" aria-hidden="true"></span>
                        </span>
                    </label>
                </form>
            `,
            `
                <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
                <button type="submit" form="schedule-form" class="btn btn-primary">Simpan</button>
            `,
        );

        document.getElementById('schedule-form')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = new FormData(event.currentTarget);
            const payload = {
                service_id: Number(form.get('service_id')),
                day: Number(form.get('day')),
                opens_at: form.get('opens_at'),
                closes_at: form.get('closes_at'),
                pre_queue_minutes: form.get('pre_queue_minutes') || 0,
                max_tickets: form.get('max_tickets') || null,
                is_available: form.get('is_available') ? 1 : 0,
            };

            await submitForm(
                schedule && !duplicate ? 'put' : 'post',
                schedule && !duplicate ? `${root.dataset.scheduleUrlBase}/${schedule.id}` : root.dataset.schedulesStoreUrl,
                payload,
            );
        }, { once: true });
    }

    function openCounterModal(counter = null) {
        const assignedStaffIds = new Set(counter?.staff_ids ?? []);

        openFormModal(
            counter ? 'Ubah Counter' : 'Tambah Counter',
            `
                <form id="counter-form" class="grid gap-4">
                    <div>
                        <label class="field-label">${requiredLabel('Nama Counter')}</label>
                        <input name="name" class="field" value="${escapeHtml(counter?.name ?? '')}" required>
                    </div>
                    <label class="switch-field">
                        <span>Counter aktif</span>
                        <span class="flex items-center">
                            <input type="checkbox" name="is_active" value="1" ${counter?.is_active ?? true ? 'checked' : ''} class="switch-input">
                            <span class="switch-control" aria-hidden="true"></span>
                        </span>
                    </label>
                    <div>
                        <label class="field-label">Petugas Counter</label>
                        <div class="rounded-md border border-stone-200 bg-white shadow-sm">
                            ${snapshot.staff_options.length > 0 ? snapshot.staff_options.map((staff) => `
                                <label class="flex items-start gap-3 border-b border-stone-200 px-3 py-2 last:border-b-0">
                                    <input
                                        type="checkbox"
                                        name="staff_ids[]"
                                        value="${staff.id}"
                                        ${assignedStaffIds.has(staff.id) ? 'checked' : ''}
                                        class="mt-1 rounded border-stone-300 text-amber-600"
                                    >
                                    <span class="min-w-0">
                                        <span class="block font-medium text-stone-800">${escapeHtml(staff.name)}</span>
                                        <span class="block text-xs text-stone-500">${escapeHtml(staff.email)}</span>
                                    </span>
                                </label>
                            `).join('') : `
                                <div class="px-3 py-2 text-sm text-stone-500">Belum ada petugas tenant yang bisa ditugaskan.</div>
                            `}
                        </div>
                        <p class="field-help">Petugas hanya dapat mengakses counter yang ditugaskan di sini.</p>
                    </div>
                </form>
            `,
            `
                <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
                <button type="submit" form="counter-form" class="btn btn-primary">Simpan</button>
            `,
        );

        document.getElementById('counter-form')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = new FormData(event.currentTarget);
            const payload = {
                name: form.get('name'),
                is_active: form.get('is_active') ? 1 : 0,
                staff_ids: Array.from(event.currentTarget.querySelectorAll('input[name="staff_ids[]"]:checked'))
                    .map((input) => Number(input.value)),
            };

            await submitForm(counter ? 'put' : 'post', counter ? `${root.dataset.counterUrlBase}/${counter.id}` : root.dataset.countersStoreUrl, payload);
        }, { once: true });
    }

    function openUserModal() {
        openFormModal(
            'Tambah Akses Admin/Petugas',
            `
                <form id="user-form" class="grid gap-4">
                    <div>
                        <label class="field-label">Nama</label>
                        <input name="name" class="field" placeholder="Isi jika membuat pengguna baru">
                        <p class="field-help">Boleh dikosongkan jika email sudah terdaftar.</p>
                    </div>
                    <div>
                        <label class="field-label">${requiredLabel('Email')}</label>
                        <input name="email" type="email" class="field" required>
                    </div>
                    <div>
                        <label class="field-label">${requiredLabel('Peran')}</label>
                        <select name="role" class="field" required>
                            <option value="admin">Admin</option>
                            <option value="staff">Petugas</option>
                        </select>
                        <p class="field-help">Admin dapat mengelola tenant. Petugas digunakan untuk operasional counter.</p>
                    </div>
                    <div>
                        <label class="field-label">Kata Sandi</label>
                        <div class="relative">
                            <input id="admin-user-password" name="password" type="password" class="field pr-11" placeholder="Wajib untuk pengguna baru">
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-stone-500 transition hover:text-stone-800"
                                data-password-toggle="#admin-user-password"
                                aria-label="Tampilkan kata sandi"
                                title="Tampilkan kata sandi"
                            >
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                        <p class="field-help">Wajib diisi hanya saat membuat pengguna baru.</p>
                    </div>
                </form>
            `,
            `
                <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
                <button type="submit" form="user-form" class="btn btn-primary">Simpan</button>
            `,
        );

        document.getElementById('user-form')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = new FormData(event.currentTarget);
            const payload = {
                name: form.get('name'),
                email: form.get('email'),
                password: form.get('password'),
                role: form.get('role'),
            };

            await submitForm('post', root.dataset.usersStoreUrl, payload);
        }, { once: true });
    }

    async function confirmDelete(url, successMessage) {
        openMessageModal(
            'Konfirmasi Hapus',
            '<p class="text-sm text-stone-700">Data yang dihapus tidak dapat dikembalikan. Lanjutkan?</p>',
            `
                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
                    <button type="button" class="btn btn-danger" data-confirm-delete>Hapus</button>
                </div>
            `,
        );

        document.querySelector('[data-confirm-delete]')?.addEventListener('click', async () => {
            closeActiveModal();

            try {
                await request('delete', url);
                showToast(successMessage, 'success');
                await refresh();
            } catch (error) {
                showToast(extractErrorMessage(error), 'error');
            }
        }, { once: true });
    }

    async function submitForm(method, url, payload) {
        try {
            const response = await request(method, url, payload);
            showToast(response.message, 'success');
            closeActiveModal();
            await refresh();
        } catch (error) {
            showToast(extractErrorMessage(error), 'error');
        }
    }
}

function formatPreQueueMinutes(minutes) {
    if (!minutes) {
        return '-';
    }

    if (minutes % 1440 === 0) {
        const days = minutes / 1440;

        return `${days} hari`;
    }

    if (minutes % 60 === 0) {
        const hours = minutes / 60;

        return `${hours} jam`;
    }

    return `${minutes} menit`;
}

function extractErrorMessage(error) {
    const firstValidationMessage = error?.response?.data?.errors
        ? Object.values(error.response.data.errors)[0]?.[0]
        : null;

    return firstValidationMessage ?? error?.response?.data?.message ?? error?.message ?? 'Permintaan gagal.';
}
