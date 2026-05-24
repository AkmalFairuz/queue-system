export function showToast(message, type = 'success') {
    const root = document.getElementById('toast-root');

    if (!root) {
        return;
    }

    const palette = {
        success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
        error: 'border-red-200 bg-red-50 text-red-900',
        info: 'border-amber-200 bg-white text-stone-900',
    };

    const toast = document.createElement('div');
    toast.className = `pointer-events-auto rounded-md border px-4 py-3 shadow-soft transition ${palette[type] ?? palette.info}`;
    toast.innerHTML = `
        <div class="flex items-start gap-3">
            <div class="pt-0.5">
                <i class="fa-solid ${type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check'}"></i>
            </div>
            <p class="text-sm font-medium leading-6">${message}</p>
        </div>
    `;

    root.appendChild(toast);

    window.setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-x-2');
        window.setTimeout(() => toast.remove(), 180);
    }, 3200);
}
