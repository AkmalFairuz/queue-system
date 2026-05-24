import { request } from './lib/http';
import { closeActiveModal, openMessageModal } from './lib/modal';
import { showToast } from './lib/toast';
import { initAdminPage } from './pages/admin';
import { initCounterPage } from './pages/counter';
import { initDisplayPage } from './pages/display';
import { initQueueTicketPage, initQueueTicketResultPage } from './pages/public-ticket';

const page = document.body.dataset.page;

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-password-toggle]');

    if (!button) {
        return;
    }

    const selector = button.dataset.passwordToggle;
    const input = selector ? document.querySelector(selector) : button.parentElement?.querySelector('input');

    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';

    const icon = button.querySelector('i');

    if (icon) {
        icon.classList.toggle('fa-eye', !isPassword);
        icon.classList.toggle('fa-eye-slash', isPassword);
    }

    button.setAttribute('aria-label', isPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    button.setAttribute('title', isPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-confirm-delete-url]');

    if (!button) {
        return;
    }

    openMessageModal(
        'Konfirmasi Hapus',
        `<p class="text-sm text-stone-700">${button.dataset.confirmDeleteMessage ?? 'Data yang dihapus tidak dapat dikembalikan. Lanjutkan?'}</p>`,
        `
            <div class="flex flex-wrap justify-end gap-3">
                <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
                <button type="button" class="btn btn-danger" data-confirm-delete-submit>Hapus</button>
            </div>
        `,
    );

    document.querySelector('[data-confirm-delete-submit]')?.addEventListener('click', async () => {
        closeActiveModal();

        try {
            const response = await request('delete', button.dataset.confirmDeleteUrl);
            showToast(response.message ?? 'Data berhasil dihapus.', 'success');

            if (response.redirect_url) {
                window.location.href = response.redirect_url;
                return;
            }

            window.location.reload();
        } catch (error) {
            showToast(
                error?.response?.data?.message
                ?? error?.message
                ?? 'Permintaan gagal.',
                'error',
            );
        }
    }, { once: true });
});

if (page === 'admin') {
    initAdminPage();
}

if (page === 'counter') {
    initCounterPage();
}

if (page === 'display') {
    initDisplayPage();
}

if (page === 'queue-ticket') {
    initQueueTicketPage();
}

if (page === 'queue-ticket-result') {
    initQueueTicketResultPage();
}
