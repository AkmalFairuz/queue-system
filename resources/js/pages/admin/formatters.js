import { badgeClass, escapeHtml, statusLabel } from '../../lib/utils';

export function requiredLabel(label) {
    return `${label}<span class="field-required">*</span>`;
}

export function tableHeader(icon, label) {
    return `
        <span class="inline-flex items-center gap-2">
            <i class="fa-solid fa-${icon} text-stone-500"></i>
            <span>${label}</span>
        </span>
    `;
}

export function renderStatusBadge(ticket) {
    return `
        <span class="${badgeClass(ticket.status)}">
            ${escapeHtml(statusLabel(ticket.status))}
            ${renderCompletedDuration(ticket)}
        </span>
    `;
}

export function renderPagination(pagination, pageTarget) {
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

export function formatPreQueueMinutes(minutes) {
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

export function extractErrorMessage(error) {
    const firstValidationMessage = error?.response?.data?.errors
        ? Object.values(error.response.data.errors)[0]?.[0]
        : null;

    return firstValidationMessage ?? error?.response?.data?.message ?? error?.message ?? 'Permintaan gagal.';
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

function disableIf(condition) {
    return condition ? 'disabled' : '';
}
