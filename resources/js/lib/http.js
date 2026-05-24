import axios from 'axios';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export async function request(method, url, data = {}) {
    const response = await axios({
        method,
        url,
        data,
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    return response.data;
}

export async function getJson(url) {
    return request('get', url);
}
