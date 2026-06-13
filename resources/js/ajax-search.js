export function initAjaxSearch(inputSelector, targetSelector, debounceMs = 400) {
    const input = document.querySelector(inputSelector);
    const target = document.querySelector(targetSelector);
    if (!input || !target) return;

    let timer;

    async function load(url) {
        target.classList.add('opacity-50', 'pointer-events-none');
        try {
            const res = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const html = await res.text();
            target.innerHTML = html;
            window.history.pushState({}, '', url);
        } catch (err) {
            console.error('Search request failed', err);
        } finally {
            target.classList.remove('opacity-50', 'pointer-events-none');
        }
    }

    function trigger() {
        clearTimeout(timer);
        timer = setTimeout(() => {
            const url = new URL(window.location.href);
            const value = input.value.trim();

            if (value) {
                url.searchParams.set('search', value);
            } else {
                url.searchParams.delete('search');
            }
            url.searchParams.delete('page'); // reset to page 1 on new search

            load(url);
        }, debounceMs);
    }

    input.addEventListener('input', trigger);

    // also handle sort/pagination links inside the target (delegated)
    target.addEventListener('click', (e) => {
        const link = e.target.closest('a[href]');
        if (!link) return;
        // only intercept links that point to the same route (pagination/sort)
        const linkUrl = new URL(link.href);
        if (linkUrl.pathname !== window.location.pathname) return;

        e.preventDefault();
        load(link.href);
    });
}