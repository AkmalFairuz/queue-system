import { dayLabel, escapeHtml } from '../../lib/utils';
import {
    formatPreQueueMinutes,
    renderPagination,
    renderStatusBadge,
    requiredLabel,
    tableHeader,
} from './formatters';

export function renderAdminSection({ root, section, snapshot }) {
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
                                        <td>${renderStatusBadge(ticket)}</td>
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
