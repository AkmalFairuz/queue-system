import { getJson } from '../lib/http';
import { subscribeToChannel } from '../lib/realtime';
import { badgeClass, escapeHtml, parseJsonScript, statusLabel } from '../lib/utils';

const TTS_DURATION_MS = 10000;

export function initDisplayPage() {
    const root = document.getElementById('display-root');

    if (!root) {
        return;
    }

    let snapshot = parseJsonScript('display-payload');
    let currentTicketIds = new Map();
    const announcementQueue = [];
    let isSpeaking = false;
    let speechWatchdog = null;
    let currentAnnouncement = null;
    let currentUtterance = null;
    let displayClockInterval = null;
    let previousServiceState = new Map();
    let hasRendered = false;

    render(snapshot, false);
    warmSpeechSynthesis();
    startRealtime();

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            window.speechSynthesis?.resume?.();
            drainAnnouncementQueue();
        }
    });

    async function refresh(announce = true, eventPayload = null) {
        try {
            snapshot = await getJson(root.dataset.snapshotUrl);
            render(snapshot, announce, eventPayload);
        } catch (error) {
            // Keep the current display state when a passive refresh fails.
        }
    }

    function render(data, announce, eventPayload = null) {
        const nextTicketIds = new Map();

        root.innerHTML = `
            <div class="flex flex-wrap items-center justify-between gap-5">
                <div class="flex min-w-0 flex-wrap items-center gap-x-5 gap-y-3">
                    <p class="text-2xl font-semibold tracking-tight text-stone-900 lg:text-4xl">${escapeHtml(data.tenant.name)}</p>
                    <span class="hidden h-2.5 w-2.5 rounded-full bg-stone-400 sm:inline-block"></span>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-3 text-xl text-stone-700 lg:text-3xl">
                        <p class="font-semibold tracking-tight text-stone-900" data-display-live-clock>--:--:--</p>
                        <span class="text-stone-400">&bull;</span>
                        <p data-display-live-date>--</p>
                    </div>
                </div>
                <span class="badge-live px-4 py-2 text-sm lg:text-lg">
                    <span class="live-dot h-4 min-h-4 w-4 min-w-4 shrink-0" aria-hidden="true"></span>
                    LIVE
                </span>
            </div>
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                ${renderServices(data.services, hasRendered ? previousServiceState : null)}
            </section>
        `;

        startDisplayClock();
        previousServiceState = buildServiceStateMap(data.services);
        hasRendered = true;

        for (const counter of data.counters) {
            const ticket = counter.current_ticket;

            if (ticket) {
                nextTicketIds.set(counter.id, ticket.id);
            }
        }

        if (announce) {
            for (const counter of data.counters) {
                const ticket = counter.current_ticket;

                if (!ticket) {
                    continue;
                }

                const shouldReplayCurrentTicket = eventPayload?.reason === 'ticket-recalled'
                    && eventPayload?.counter_id === counter.id
                    && eventPayload?.ticket_id === ticket.id;

                if (currentTicketIds.get(counter.id) !== ticket.id || shouldReplayCurrentTicket) {
                    enqueueAnnouncement(ticket.tts_text, data.tenant.tts_language);
                }
            }
        }

        currentTicketIds = nextTicketIds;
    }

    function renderServices(services = [], previousState = null) {
        if (services.length === 0) {
            return '<div class="empty-state lg:col-span-2">Belum ada layanan.</div>';
        }

        return services.map((service) => {
            const ticket = service.last_called_ticket;
            const serviceState = serviceSnapshotState(service);
            const previous = previousState?.get(service.id) ?? null;
            const waitingChanged = previous ? previous.waiting !== serviceState.waiting : false;
            const servingChanged = previous ? previous.serving !== serviceState.serving : false;
            const lastCalledChanged = previous ? previous.lastCalledTicketId !== serviceState.lastCalledTicketId : false;

            return `
                <article class="panel overflow-hidden">
                    <div class="panel-header border-b border-amber-700 bg-amber-600 py-3">
                        <div class="text-center">
                            <h2 class="text-xl font-semibold tracking-tight text-white">${escapeHtml(service.name)}</h2>
                        </div>
                    </div>
                    <div class="panel-body space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            ${renderServiceStats(service.stats, { waitingChanged, servingChanged })}
                        </div>
                        <div class="flex min-h-[150px] flex-col justify-center rounded-md border border-stone-200 bg-stone-50 px-4 py-4 text-center ${changeClass(lastCalledChanged)}">
                            <p class="text-sm uppercase tracking-[0.18em] text-stone-500">Panggilan Terakhir</p>
                            ${ticket ? `
                                <p class="mt-3 text-5xl font-semibold tracking-tight text-stone-900">${escapeHtml(ticket.queue_number)}</p>
                                <p class="mt-2 text-lg font-semibold text-stone-800 lg:text-xl">${ticket.counter ? escapeHtml(ticket.counter.name) : 'Belum ada counter'}</p>
                            ` : `
                                <p class="mt-3 text-sm text-stone-600">Belum ada panggilan untuk layanan ini.</p>
                            `}
                        </div>
                    </div>
                </article>
            `;
        }).join('');
    }

    function renderServiceStats(stats = {}, changed = {}) {
        return [
            ['Menunggu', stats.waiting ?? 0, changed.waitingChanged],
            ['Dilayani', stats.serving ?? 0, changed.servingChanged],
        ].map(([label, total, isChanged]) => `
            <div class="rounded-md border border-stone-200 bg-white px-4 py-3 ${changeClass(Boolean(isChanged))}">
                <p class="text-sm uppercase tracking-[0.18em] text-stone-500">${label}</p>
                <p class="mt-2 text-2xl font-semibold text-stone-900">${total}</p>
            </div>
        `).join('');
    }

    function startRealtime() {
        subscribeToChannel({
            channelName: root.dataset.channel,
            eventName: 'queue.display.updated',
            onMessage: (eventPayload) => refresh(true, eventPayload),
            onFallback: () => refresh(false),
        });
    }

    function enqueueAnnouncement(text, language) {
        if (!text) {
            return;
        }

        announcementQueue.push({ text, language });
        drainAnnouncementQueue();
    }

    function drainAnnouncementQueue() {
        if (!('speechSynthesis' in window) || isSpeaking || announcementQueue.length === 0) {
            return;
        }

        window.speechSynthesis.resume();

        const nextAnnouncement = announcementQueue.shift();

        if (!nextAnnouncement) {
            return;
        }

        isSpeaking = true;
        currentAnnouncement = nextAnnouncement;
        currentUtterance = null;

        const utterance = new SpeechSynthesisUtterance(nextAnnouncement.text);
        utterance.lang = nextAnnouncement.language;
        utterance.rate = 0.95;
        utterance.voice = selectVoice(nextAnnouncement.language);
        utterance.onstart = () => {
            currentUtterance = utterance;
        };
        utterance.onend = () => {
            currentUtterance = null;
        };
        utterance.onerror = () => {
            currentUtterance = null;
            finishAnnouncement('error');
        };

        speechWatchdog = window.setTimeout(() => {
            finishAnnouncement('fixed-duration');
        }, TTS_DURATION_MS);

        if (window.speechSynthesis.pending && !window.speechSynthesis.speaking) {
            window.speechSynthesis.cancel();
        }

        window.speechSynthesis.speak(utterance);
    }

    function finishAnnouncement(reason) {
        if (speechWatchdog) {
            window.clearTimeout(speechWatchdog);
            speechWatchdog = null;
        }

        const completedAnnouncement = currentAnnouncement;
        currentAnnouncement = null;
        currentUtterance = null;

        if ('speechSynthesis' in window && (window.speechSynthesis.speaking || window.speechSynthesis.pending)) {
            window.speechSynthesis.cancel();
        }

        if (!isSpeaking) {
            return;
        }

        isSpeaking = false;
        if (completedAnnouncement) {
            root.dataset.lastTtsDurationMs = String(TTS_DURATION_MS);
            root.dataset.lastTtsText = completedAnnouncement.text;

            window.dispatchEvent(new CustomEvent('queue-display-tts-ended', {
                detail: {
                    text: completedAnnouncement.text,
                    language: completedAnnouncement.language,
                    duration_ms: TTS_DURATION_MS,
                    reason,
                },
            }));
        }

        window.setTimeout(() => {
            window.speechSynthesis?.resume?.();
            drainAnnouncementQueue();
        }, 75);
    }

    function warmSpeechSynthesis() {
        if (!('speechSynthesis' in window)) {
            return;
        }

        window.speechSynthesis.getVoices();

        if (typeof window.speechSynthesis.onvoiceschanged !== 'undefined') {
            window.speechSynthesis.onvoiceschanged = () => {
                window.speechSynthesis.getVoices();
            };
        }
    }

    function startDisplayClock() {
        const clockElement = root.querySelector('[data-display-live-clock]');
        const dateElement = root.querySelector('[data-display-live-date]');

        if (displayClockInterval) {
            window.clearInterval(displayClockInterval);
            displayClockInterval = null;
        }

        if (!(clockElement instanceof HTMLElement) || !(dateElement instanceof HTMLElement)) {
            return;
        }

        const update = () => {
            const now = new Date();

            clockElement.textContent = formatDisplayClock(now);
            dateElement.textContent = formatDisplayDate(now);
        };

        update();
        displayClockInterval = window.setInterval(update, 1000);
    }

    function selectVoice(language) {
        if (!('speechSynthesis' in window)) {
            return null;
        }

        const voices = window.speechSynthesis.getVoices();

        return voices.find((voice) => voice.lang === language)
            ?? voices.find((voice) => voice.lang?.startsWith(language.split('-')[0]))
            ?? null;
    }
}

function buildServiceStateMap(services = []) {
    return new Map(services.map((service) => [service.id, serviceSnapshotState(service)]));
}

function serviceSnapshotState(service) {
    return {
        waiting: service.stats?.waiting ?? 0,
        serving: service.stats?.serving ?? 0,
        lastCalledTicketId: service.last_called_ticket?.id ?? null,
    };
}

function changeClass(isChanged) {
    return isChanged ? 'display-change-ripple' : '';
}

function formatDisplayClock(date) {
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');

    return `${hours}:${minutes}:${seconds}`;
}

function formatDisplayDate(date) {
    const day = date.getDate();
    const month = date.getMonth() + 1;
    const year = date.getFullYear();

    return `${day}/${month}/${year}`;
}
