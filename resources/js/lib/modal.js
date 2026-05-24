let activeKeydownHandler = null;

function closeModal() {
    document.getElementById('modal-root')?.replaceChildren();
    document.body.classList.remove('overflow-hidden');

    if (activeKeydownHandler) {
        document.removeEventListener('keydown', activeKeydownHandler);
        activeKeydownHandler = null;
    }
}

function openModal(title, body, footer = '') {
    const root = document.getElementById('modal-root');

    if (!root) {
        return;
    }

    document.body.classList.add('overflow-hidden');

    const wrapper = document.createElement('div');
    wrapper.className = 'fixed inset-0 z-50 flex items-center justify-center bg-stone-900/55 px-4 py-6';
    wrapper.innerHTML = `
        <div class="panel w-full max-w-2xl overflow-hidden">
            <div class="panel-header flex items-start justify-between gap-4">
                <div>
                    <h2 class="section-title">${title}</h2>
                </div>
                <button type="button" class="btn btn-muted px-3 py-2" data-close-modal>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="panel-body">${body}</div>
            ${footer ? `<div class="border-t border-stone-200 px-5 py-4">${footer}</div>` : ''}
        </div>
    `;

    wrapper.addEventListener('click', (event) => {
        if (event.target === wrapper || event.target.closest('[data-close-modal]')) {
            closeModal();
        }
    });

    if (activeKeydownHandler) {
        document.removeEventListener('keydown', activeKeydownHandler);
    }

    activeKeydownHandler = (event) => {
        if (event.key === 'Escape') {
            closeModal();
        }
    };

    document.addEventListener('keydown', activeKeydownHandler);
    root.replaceChildren(wrapper);
}

export function openFormModal(title, body, actions) {
    openModal(title, body, `<div class="flex flex-wrap justify-end gap-3">${actions}</div>`);
}

export function openMessageModal(title, body, actions = '<button type="button" class="btn btn-secondary" data-close-modal>Tutup</button>') {
    openModal(title, body, `<div class="flex flex-wrap justify-end gap-3">${actions}</div>`);
}

export function closeActiveModal() {
    closeModal();
}
