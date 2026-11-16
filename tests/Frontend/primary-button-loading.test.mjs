import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import vm from 'node:vm';

// Exercise the Alpine state emitted by the real shared component, with no live DB.
const rendered = JSON.parse(execFileSync('php', ['-r', `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
echo json_encode([
    'standard' => Illuminate\\Support\\Facades\\Blade::render('<x-form.primary-button loading="loading">Save</x-form.primary-button>'),
    'custom' => Illuminate\\Support\\Facades\\Blade::render('<x-form.primary-button loading="generating">Generate</x-form.primary-button>'),
    'search' => Illuminate\\Support\\Facades\\Blade::render('<x-table.search />'),
    'advanced' => (function () {
        request()->merge(['utility_room' => 'R1', 'utility_status' => 'active', 'utility_amount_min' => '0', 'utility_recorded_from' => '2026-11-15']);
        return Illuminate\\Support\\Facades\\Blade::render('<x-table.search :is-advance-search="true" :show-utility-filters="true" :custom-filter-defaults="$defaults" />', [
            'defaults' => array_fill_keys(array_keys(App\\Models\\UtilityService::filterRules()), ''),
        ]);
    })(),
    'action' => Illuminate\\Support\\Facades\\Blade::render(<<<'BLADE'
<x-form.primary-button loading="subAction === 'approve'">Approve</x-form.primary-button>
BLADE),
]);
`], {
    encoding: 'utf8', cwd: new URL('../../', import.meta.url),
    env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', CACHE_STORE: 'array', SESSION_DRIVER: 'array' },
}));

function attribute(html, name) {
    const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return html.match(new RegExp(`${escaped}="([\\s\\S]*?)"`))?.[1]
        ?.replaceAll('&quot;', '"').replaceAll('&#039;', "'").replaceAll('&gt;', '>').replaceAll('&lt;', '<').replaceAll('&amp;', '&');
}

function button(form = null) {
    const state = vm.runInNewContext(`(${attribute(rendered.standard, 'x-data')})`);
    state.$el = { form };
    return state;
}

test('only the submitted button loads, even when other buttons share its form', () => {
    const form = {};
    const first = button(form), second = button(form), other = button({});
    const submit = { target: form, submitter: second.$el };
    for (const state of [first, second, other]) {
        state.selectSubmitter(submit);
        state.syncFormLoading({ target: form, detail: true });
    }
    assert.equal(first.loading, false);
    assert.equal(second.loading, true);
    assert.equal(other.loading, false);
});

test('native forms start the spinner after a valid submit and select a default for keyboard submission', () => {
    const form = {};
    const first = button(form), second = button(form);
    form.querySelector = () => first.$el;
    for (const state of [first, second]) {
        const event = { target: form, submitter: null, defaultPrevented: false };
        state.selectSubmitter(event);
        state.startNativeSubmit(event);
    }
    assert.equal(first.loading, true);
    assert.equal(second.loading, false);
});

test('cancelled submissions stay idle while explicit async events can start and stop the spinner', () => {
    const form = {}, state = button(form);
    const event = { target: form, submitter: state.$el, defaultPrevented: true };
    state.selectSubmitter(event);
    state.startNativeSubmit(event);
    assert.equal(state.loading, false);
    state.syncFormLoading({ target: form, detail: true });
    assert.equal(state.loading, true);
    state.syncFormLoading({ target: {}, detail: false });
    assert.equal(state.loading, true);
    state.syncFormLoading({ target: form, detail: false });
    assert.equal(state.loading, false);
});

test('dialog buttons outside forms ignore unrelated form events', () => {
    const state = button();
    const event = { target: {}, submitter: state.$el };
    state.selectSubmitter(event);
    state.startNativeSubmit(event);
    state.syncFormLoading({ ...event, detail: true });
    assert.equal(state.loading, false);
});

test('custom loading variables and per-action expressions keep their parent Alpine state', () => {
    assert.equal(attribute(rendered.custom, 'x-data'), undefined);
    assert.equal(attribute(rendered.custom, 'x-show'), 'generating');
    assert.equal(attribute(rendered.action, 'x-data'), undefined);
    assert.equal(attribute(rendered.action, 'x-show'), "subAction === 'approve'");
    assert.equal(attribute(rendered.standard, ':aria-busy'), 'loading');
    assert.equal(attribute(rendered.standard, '@form-loading.window.capture'), 'syncFormLoading($event)');
});

test('Search shows its loading state before starting navigation', () => {
    const html = rendered.search.slice(rendered.search.indexOf('<!-- Search Button'));
    const callbacks = [];
    let submitted = false;
    const form = { submit() { submitted = true; } };
    const state = vm.runInNewContext(`(${attribute(html, 'x-data')})`);
    state.$el = { closest: () => form };
    state.$nextTick = callback => callbacks.push(callback);
    const context = vm.createContext({ state, requestAnimationFrame: callback => callbacks.push(callback) });
    vm.runInContext(`with (state) { ${attribute(html, '@click')} }`, context);
    assert.equal(state.loading, true);
    assert.equal(submitted, false);
    callbacks.shift()(); // Alpine applies the spinner/disabled state.
    callbacks.shift()(); // Allow a browser paint before the navigation frame.
    assert.equal(submitted, false);
    callbacks.shift()();
    assert.equal(submitted, true);
    assert.equal(state.loading, true);
});

test('advanced Reset clears utility text, selection, numeric and date filters in its own search component', () => {
    let cleared = 0;
    const dates = Array.from({ length: 4 }, () => ({ _flatpickr: { clear() { cleared++; } } }));
    const context = vm.createContext({ document: { getElementById: () => null } });
    const state = vm.runInContext(`(${attribute(rendered.advanced, 'x-data')})`, context);
    state.$refs = { searchRoot: { querySelectorAll: () => dates } };
    assert.equal(state.customFilters.utility_room, 'R1');
    assert.equal(state.customFilters.utility_status, 'active');
    assert.equal(state.customFilters.utility_amount_min, '0');
    state.resetFilters();
    assert.equal(Object.values(state.customFilters).every(value => value === ''), true);
    assert.equal(cleared, 4);
    assert.equal(Object.hasOwn(state.customFilters, 'management_user_id'), false);
    assert.equal(Object.hasOwn(state.customFilters, 'type'), false);
});

test('the shared search includes utility filters only when explicitly enabled', () => {
    assert.doesNotMatch(rendered.search, /name="utility_room"/);
    assert.match(rendered.advanced, /name="utility_room"/);
    assert.match(rendered.advanced, /name="utility_tenant"/);
    assert.match(rendered.advanced, /name="utility_amount_min"/);
});
