import { request } from '../../lib/http';
import { closeActiveModal, openFormModal, openMessageModal } from '../../lib/modal';
import { showToast } from '../../lib/toast';
import { dayLabel, escapeHtml } from '../../lib/utils';
import { extractErrorMessage, requiredLabel } from './formatters';

export function openServiceModal(context, service = null) {
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

        await submitForm(
            context,
            service ? 'put' : 'post',
            service ? `${context.root.dataset.serviceUrlBase}/${service.id}` : context.root.dataset.servicesStoreUrl,
            payload,
        );
    }, { once: true });
}

export function openScheduleModal(context, serviceId, scheduleId = null, duplicate = false) {
    const schedule = scheduleId ? context.findSchedule(serviceId, scheduleId) : null;

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
            context,
            schedule && !duplicate ? 'put' : 'post',
            schedule && !duplicate ? `${context.root.dataset.scheduleUrlBase}/${schedule.id}` : context.root.dataset.schedulesStoreUrl,
            payload,
        );
    }, { once: true });
}

export function openCounterModal(context, counter = null) {
    const assignedStaffIds = new Set(counter?.staff_ids ?? []);
    const snapshot = context.getSnapshot();

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

        await submitForm(
            context,
            counter ? 'put' : 'post',
            counter ? `${context.root.dataset.counterUrlBase}/${counter.id}` : context.root.dataset.countersStoreUrl,
            payload,
        );
    }, { once: true });
}

export function openUserModal(context) {
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

        await submitForm(context, 'post', context.root.dataset.usersStoreUrl, payload);
    }, { once: true });
}

export function confirmDelete(context, url, successMessage) {
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
            await context.refresh();
        } catch (error) {
            showToast(extractErrorMessage(error), 'error');
        }
    }, { once: true });
}

async function submitForm(context, method, url, payload) {
    try {
        const response = await request(method, url, payload);
        showToast(response.message, 'success');
        closeActiveModal();
        await context.refresh();
    } catch (error) {
        showToast(extractErrorMessage(error), 'error');
    }
}
