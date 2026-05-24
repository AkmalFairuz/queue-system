export function parseJsonScript(id) {
    const node = document.getElementById(id);

    if (!node) {
        return null;
    }

    return JSON.parse(node.textContent ?? 'null');
}

export function badgeClass(status) {
    return {
        waiting: 'badge badge-waiting',
        called: 'badge badge-called',
        serving: 'badge badge-serving',
        completed: 'badge badge-completed',
        skipped: 'badge badge-skipped',
        cancelled: 'badge badge-cancelled',
    }[status] ?? 'badge badge-completed';
}

export function statusLabel(status) {
    return {
        waiting: 'Menunggu',
        called: 'Dipanggil',
        serving: 'Dilayani',
        completed: 'Selesai',
        skipped: 'Dilewati',
        cancelled: 'Dibatalkan',
    }[status] ?? status;
}

export function dayLabel(day) {
    return ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'][day] ?? '-';
}

export function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
