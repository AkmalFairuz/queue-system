import { getJson, request } from '../lib/http';
import { showToast } from '../lib/toast';
import { parseJsonScript } from '../lib/utils';
import { extractErrorMessage } from './admin/formatters';
import { confirmDelete, openCounterModal, openScheduleModal, openServiceModal, openUserModal } from './admin/modals';
import { renderAdminSection } from './admin/render';

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

        const modalContext = buildModalContext();
        const handlers = {
            'service-create': () => openServiceModal(modalContext),
            'service-edit': () => openServiceModal(modalContext, findService(button.dataset.id)),
            'service-delete': () => confirmDelete(modalContext, `${root.dataset.serviceUrlBase}/${button.dataset.id}`, 'Layanan berhasil dihapus.'),
            'schedule-create': () => openScheduleModal(modalContext, button.dataset.serviceId),
            'schedule-edit': () => openScheduleModal(modalContext, button.dataset.serviceId, button.dataset.scheduleId),
            'schedule-duplicate': () => openScheduleModal(modalContext, button.dataset.serviceId, button.dataset.scheduleId, true),
            'schedule-delete': () => confirmDelete(modalContext, `${root.dataset.scheduleUrlBase}/${button.dataset.scheduleId}`, 'Jadwal layanan berhasil dihapus.'),
            'counter-create': () => openCounterModal(modalContext),
            'counter-edit': () => openCounterModal(modalContext, findCounter(button.dataset.id)),
            'counter-delete': () => confirmDelete(modalContext, `${root.dataset.counterUrlBase}/${button.dataset.id}`, 'Counter berhasil dihapus.'),
            'user-create': () => openUserModal(modalContext),
            'user-delete': () => confirmDelete(modalContext, `${root.dataset.userUrlBase}/${button.dataset.id}`, 'Akses pengguna berhasil dihapus.'),
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
            const payload = Object.fromEntries(new FormData(form).entries());
            const response = await request('put', root.dataset.settingsUrl, payload);
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
        root.innerHTML = renderAdminSection({ root, section, snapshot });
    }

    function buildModalContext() {
        return {
            root,
            refresh,
            getSnapshot: () => snapshot,
            findSchedule,
        };
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
}
