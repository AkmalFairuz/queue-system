import { getJson, request } from '../lib/http';
import { subscribeToChannel } from '../lib/realtime';
import { showToast } from '../lib/toast';
import { escapeHtml, parseJsonScript } from '../lib/utils';

export function initQueueTicketPage() {
    const root = document.getElementById('queue-ticket-root');

    if (!root) {
        return;
    }

    const payload = parseJsonScript('queue-ticket-payload');
    const details = root.querySelector('[data-queue-ticket-details]');
    const service = payload?.service;

    if (!(details instanceof HTMLElement) || !service) {
        return;
    }

    let selectedDateKey = service.date_options?.[0]?.key ?? '';
    let visibleMonthKey = selectedDateKey ? monthKeyForDateKey(selectedDateKey) : '';

    details.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action]');

        if (!button) {
            return;
        }

        if (button.dataset.action === 'calendar-nav') {
            const nextMonthKey = shiftMonthKey(visibleMonthKey, button.dataset.direction === 'next' ? 1 : -1);

            if (!isMonthWithinRange(nextMonthKey, service.date_options ?? [])) {
                return;
            }

            visibleMonthKey = nextMonthKey;
            render();
            return;
        }

        if (button.dataset.action === 'select-date') {
            selectedDateKey = button.dataset.dateKey;
            render();
        }
    });

    details.addEventListener('change', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLSelectElement) || target.name !== 'service_schedule_id') {
            return;
        }
    });

    details.addEventListener('submit', async (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || form.id !== 'queue-ticket-form') {
            return;
        }

        event.preventDefault();

        const submitButton = form.querySelector('button[type="submit"]');

        if (submitButton instanceof HTMLButtonElement) {
            submitButton.disabled = true;
        }

        const formData = new FormData(form);

        try {
            const response = await request('post', root.dataset.submitUrl, {
                service_id: service.id,
                service_schedule_id: formData.get('service_schedule_id'),
            });

            showToast(response.message, 'success');

            if (response.redirect_url) {
                window.location.href = response.redirect_url;
            }
        } catch (error) {
            showToast(extractErrorMessage(error), 'error');
        } finally {
            if (submitButton instanceof HTMLButtonElement) {
                submitButton.disabled = false;
            }
        }
    });

    render();

    function render() {
        const availableDateKeys = new Set((service.date_options ?? []).map((option) => option.key));
        const scheduleOptions = buildScheduleOptions(service, selectedDateKey);

        details.innerHTML = `
            <div class="space-y-5">
                <form id="queue-ticket-form" class="grid gap-5">
                    <div>
                        <label class="field-label">Tanggal Layanan</label>
                        <div class="max-w-[600px] rounded-md border border-stone-200 bg-white p-3 shadow-sm">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <button
                                    type="button"
                                    class="btn btn-secondary px-3 py-1"
                                    data-action="calendar-nav"
                                    data-direction="prev"
                                    ${disableIf(!isMonthWithinRange(shiftMonthKey(visibleMonthKey, -1), service.date_options ?? []))}
                                >
                                    <i class="fa-solid fa-chevron-left"></i>
                                </button>
                                <p class="text-sm font-semibold text-stone-900">${escapeHtml(formatMonthLabel(visibleMonthKey))}</p>
                                <button
                                    type="button"
                                    class="btn btn-secondary px-3 py-1"
                                    data-action="calendar-nav"
                                    data-direction="next"
                                    ${disableIf(!isMonthWithinRange(shiftMonthKey(visibleMonthKey, 1), service.date_options ?? []))}
                                >
                                    <i class="fa-solid fa-chevron-right"></i>
                                </button>
                            </div>
                            <div class="mb-2 grid grid-cols-7 gap-1 text-center text-xs font-medium uppercase tracking-[0.14em] text-stone-500">
                                ${['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'].map((day) => `<span>${day}</span>`).join('')}
                            </div>
                            <div class="grid grid-cols-7 gap-1">
                                ${buildCalendarCells(visibleMonthKey, selectedDateKey, availableDateKeys)}
                            </div>
                            <div class="mt-3 flex flex-wrap gap-3 text-xs text-stone-600">
                                <span class="inline-flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                                    Tersedia
                                </span>
                                <span class="inline-flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full bg-stone-300"></span>
                                    Tidak tersedia
                                </span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="field-label">Jadwal Tersedia</label>
                        <select name="service_schedule_id" class="field" required>
                            ${scheduleOptions}
                        </select>
                        <p class="field-help">Pilih jam layanan yang tersedia pada tanggal terpilih.</p>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-ticket-simple"></i>
                            Ambil Antrian
                        </button>
                    </div>
                </form>
            </div>
        `;
    }
}

export function initQueueTicketResultPage() {
    const root = document.getElementById('queue-ticket-result-root');

    if (!root) {
        return;
    }

    const payload = parseJsonScript('queue-ticket-result-payload');
    let service = payload?.service ?? null;
    const serviceId = Number(root.dataset.serviceId);
    const serviceCard = root.querySelector('[data-last-called-ticket]');

    if (!(serviceCard instanceof HTMLElement) || !serviceId) {
        return;
    }

    renderServiceCard(service);
    startRealtime();

    function renderServiceCard(serviceData) {
        serviceCard.innerHTML = serviceData
            ? `
                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm uppercase tracking-[0.18em] text-stone-500">Panggilan Terakhir</p>
                        <span class="badge-live">
                            <span class="live-dot" aria-hidden="true"></span>
                            LIVE
                        </span>
                    </div>
                    <div class="rounded-md border border-stone-200 bg-stone-50 px-4 py-5 text-center">
                        ${serviceData.last_called_ticket ? `
                            <p class="text-5xl font-semibold tracking-tight text-stone-900">${escapeHtml(serviceData.last_called_ticket.queue_number)}</p>
                            <p class="mt-2 text-sm font-medium text-stone-700">${escapeHtml(serviceData.name)}</p>
                            <p class="mt-1 text-sm text-stone-500">${escapeHtml(serviceData.last_called_ticket.counter?.name ?? 'Belum ada counter')}</p>
                        ` : `
                            <p class="text-sm text-stone-600">Belum ada panggilan untuk layanan ini.</p>
                        `}
                    </div>
                </div>
            `
            : '<div class="empty-state">Data panggilan terakhir tidak tersedia.</div>';
    }

    async function refresh() {
        try {
            const snapshot = await getJson(root.dataset.snapshotUrl);
            service = snapshot.services?.find((item) => item.id === serviceId) ?? null;
            renderServiceCard(service);
        } catch (error) {
            // Keep the current rendered state when passive refresh fails.
        }
    }

    function startRealtime() {
        subscribeToChannel({
            channelName: root.dataset.channel,
            eventName: 'queue.display.updated',
            onMessage: () => refresh(),
            onFallback: () => refresh(),
        });
    }
}

function buildScheduleOptions(service, dateKey) {
    const queueOptions = (service.queue_options ?? []).filter((option) => option.date_key === dateKey);

    return queueOptions.map((option) => `
        <option value="${escapeHtml(String(option.service_schedule_id))}">
            ${escapeHtml(formatScheduleOptionLabel(option))}
        </option>
    `).join('');
}

function formatScheduleOptionLabel(option) {
    if (option.remaining_quota === null || option.remaining_quota === undefined) {
        return option.schedule_label;
    }

    return `${option.schedule_label} · Sisa kuota: ${option.remaining_quota}`;
}

function buildCalendarCells(monthKey, selectedDateKey, availableDateKeys) {
    const [year, month] = monthKey.split('-').map(Number);
    const firstDay = new Date(year, month - 1, 1);
    const daysInMonth = new Date(year, month, 0).getDate();
    const leadingEmptyCells = (firstDay.getDay() + 6) % 7;
    const cells = [];

    for (let index = 0; index < leadingEmptyCells; index += 1) {
        cells.push('<span></span>');
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
        const dateKey = `${monthKey}-${String(day).padStart(2, '0')}`;
        const isAvailable = availableDateKeys.has(dateKey);
        const isSelected = dateKey === selectedDateKey;

        if (!isAvailable) {
            cells.push(`
                <span class="flex h-10 items-center justify-center rounded-md border border-stone-200 bg-stone-100 text-sm text-stone-400">
                    ${day}
                </span>
            `);
            continue;
        }

        cells.push(`
            <button
                type="button"
                class="flex h-10 items-center justify-center rounded-md border text-sm font-medium transition ${isSelected ? 'border-amber-600 bg-amber-600 text-white' : 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100'}"
                data-action="select-date"
                data-date-key="${dateKey}"
            >
                ${day}
            </button>
        `);
    }

    return cells.join('');
}

function formatMonthLabel(monthKey) {
    const [year, month] = monthKey.split('-').map(Number);

    return new Intl.DateTimeFormat('id-ID', {
        month: 'long',
        year: 'numeric',
    }).format(new Date(year, month - 1, 1));
}

function monthKeyForDateKey(dateKey) {
    return dateKey.slice(0, 7);
}

function shiftMonthKey(monthKey, delta) {
    const [year, month] = monthKey.split('-').map(Number);
    const date = new Date(year, month - 1 + delta, 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function isMonthWithinRange(monthKey, dateOptions) {
    if (dateOptions.length === 0) {
        return false;
    }

    const minMonthKey = monthKeyForDateKey(dateOptions[0].key);
    const maxMonthKey = monthKeyForDateKey(dateOptions[dateOptions.length - 1].key);

    return monthKey >= minMonthKey && monthKey <= maxMonthKey;
}

function disableIf(condition) {
    return condition ? 'disabled' : '';
}

function extractErrorMessage(error) {
    const firstValidationMessage = error?.response?.data?.errors
        ? Object.values(error.response.data.errors)[0]?.[0]
        : null;

    return firstValidationMessage ?? error?.response?.data?.message ?? error?.message ?? 'Permintaan gagal.';
}
