export function serviceRequestNavigation(url) {
    let timer;
    let controller;
    let active = true;
    let refreshAgain = false;
    let refresh;
    let pollDelay = 1000;

    return {
        open: false,
        unreadCount: 0,
        init() {
            if (!url) return;
            refresh = () => this.refreshUnread();
            document.addEventListener('visibilitychange', refresh);
            window.addEventListener('pageshow', refresh);
            window.addEventListener('service-request-read', refresh);
            this.refreshUnread();
        },
        async refreshUnread() {
            clearTimeout(timer);
            if (!active || !url || document.visibilityState !== 'visible') return;
            if (controller) { refreshAgain = true; return; }
            controller = new AbortController();
            let succeeded = false;
            try {
                const response = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store', signal: controller.signal,
                });
                if ([401, 403, 419].includes(response.status)) { active = false; this.unreadCount = 0; return; }
                if (!response.ok) return;
                const data = await response.json();
                if (active && Number.isInteger(data.unread_count) && data.unread_count >= 0) {
                    this.unreadCount = data.unread_count;
                    succeeded = true;
                }
            } catch {
                // Preserve the last known state until the next refresh succeeds.
            } finally {
                controller = null;
                pollDelay = succeeded ? 1000 : Math.min(pollDelay * 2, 30000);
                if (active) timer = setTimeout(refresh, refreshAgain && succeeded ? 0 : pollDelay);
                refreshAgain = false;
            }
        },
        destroy() {
            active = false;
            clearTimeout(timer);
            controller?.abort();
            document.removeEventListener('visibilitychange', refresh);
            window.removeEventListener('pageshow', refresh);
            window.removeEventListener('service-request-read', refresh);
        },
    };
}
