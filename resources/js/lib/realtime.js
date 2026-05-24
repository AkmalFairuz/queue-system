import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let sharedEcho = null;
let didAttemptEchoBoot = false;

export function subscribeToChannel({
    channelName,
    eventName,
    onMessage,
    onFallback = null,
    fallbackInterval = 15000,
}) {
    const echo = ensureEcho();
    let intervalId = onFallback ? window.setInterval(onFallback, fallbackInterval) : null;
    let channel = null;

    if (echo) {
        channel = echo.channel(channelName);
        channel.listen(`.${eventName}`, onMessage);
    }

    return () => {
        if (channel) {
            channel.stopListening(`.${eventName}`);
        }

        if (intervalId) {
            window.clearInterval(intervalId);
        }
    };
}

function ensureEcho() {
    if (didAttemptEchoBoot) {
        return sharedEcho;
    }

    didAttemptEchoBoot = true;

    const key = document.querySelector('meta[name="reverb-key"]')?.content;
    const host = document.querySelector('meta[name="reverb-host"]')?.content;
    const port = Number(document.querySelector('meta[name="reverb-port"]')?.content ?? 8080);
    const scheme = document.querySelector('meta[name="reverb-scheme"]')?.content ?? 'http';

    if (!key || !host) {
        sharedEcho = null;

        return sharedEcho;
    }

    try {
        window.Pusher = Pusher;

        sharedEcho = new Echo({
            broadcaster: 'reverb',
            key,
            wsHost: host,
            wsPort: port,
            wssPort: port,
            forceTLS: scheme === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    } catch (error) {
        sharedEcho = null;
    }

    return sharedEcho;
}
