import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import vm from 'node:vm';

// Exercise the actual Blade-generated Alpine API rather than a second keyboard implementation.
const rendered = JSON.parse(execFileSync('php', ['-r', `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
echo json_encode([
    'complaint' => Illuminate\\Support\\Facades\\Blade::render('<x-customer-service.create-modal action="/complaint" />'),
    'normal' => Illuminate\\Support\\Facades\\Blade::render('<x-form.form action="/ordinary"><x-form.primary-button loading="loading">Save</x-form.primary-button></x-form.form>'),
]);
`], { encoding: 'utf8', cwd: new URL('../../', import.meta.url) }));

const attribute = (html, name) => html.match(new RegExp(name + '="([^"]*)"'))?.[1];
function form(html) {
    let submitted = 0;
    const field = (tagName) => ({ tagName, offsetWidth: 100, offsetHeight: 30, value: 'draft',
        focus() { this.focused = true; }, setSelectionRange(start, end) { this.selection = [start, end]; } });
    const subject = field('INPUT');
    const details = field('TEXTAREA');
    const hiddenFile = { ...field('INPUT'), offsetWidth: 0, offsetHeight: 0 };
    const context = vm.createContext({
        $el: { querySelectorAll: () => [subject, details, hiddenFile], requestSubmit: () => submitted++ },
        setTimeout: (callback) => callback(),
    });
    context.state = vm.runInContext('(' + attribute(html, 'x-data') + ')', context);
    const run = (name, event = {}) => {
        context.$event = event;
        vm.runInContext('with (state) { ' + attribute(html, name) + ' }', context);
    };
    const enter = (target, extra = {}) => {
        const event = { target, prevented: false, preventDefault() { this.prevented = true; }, ...extra };
        run('@keydown.enter', event);
        return event;
    };
    return { subject, details, enter, run, state: context.state, submitted: () => submitted };
}

test('Complaint uses the shared form to move Subject to Details and preserves textarea newlines', () => {
    const f = form(rendered.complaint);
    assert.equal(f.enter(f.subject).prevented, true);
    assert.equal(f.details.focused, true);
    assert.deepEqual(f.details.selection, [5, 5]);
    assert.equal(f.enter(f.details).prevented, false);
    assert.equal(f.submitted(), 0);
    assert.equal(f.state.loading, false);
});

test('Complaint IME Enter neither moves focus nor submits, including legacy keyCode 229', () => {
    const f = form(rendered.complaint);
    f.run('@compositionstart');
    f.enter(f.subject);
    f.run('@compositionend');
    f.enter(f.subject, { isComposing: true });
    f.enter(f.subject, { keyCode: 229 });
    assert.equal(f.details.focused, undefined);
    assert.equal(f.submitted(), 0);
    f.run('@compositionstart');
    let stopped = false;
    let prevented = false;
    f.run('@submit.capture', { preventDefault() { prevented = true; }, stopImmediatePropagation() { stopped = true; } });
    assert.equal(prevented && stopped, true);
});

test('Async loading drives the existing primary button and does not install a second submit handler', () => {
    const f = form(rendered.complaint);
    assert.equal(attribute(rendered.complaint, '@submit'), undefined);
    assert.match(rendered.complaint, /:disabled="loading"/);
    assert.match(rendered.complaint, /<svg x-show="loading"/);
    assert.equal((rendered.complaint.match(/name="_token"/g) || []).length, 1);
    f.run('@form-loading', { detail: true });
    assert.equal(f.state.loading, true);
    f.run('@form-loading', { detail: false });
    assert.equal(f.state.loading, false);
    assert.equal(f.submitted(), 0);
});

test('Default shared-form Enter and submit behavior stays unchanged', () => {
    const f = form(rendered.normal);
    assert.equal(attribute(rendered.normal, '@compositionstart'), undefined);
    assert.equal(attribute(rendered.normal, '@form-loading'), undefined);
    assert.equal(f.enter(f.details).prevented, true);
    assert.equal(f.submitted(), 1);
    assert.equal(f.state.loading, true);
    f.state.loading = false;
    f.run('@submit');
    assert.equal(f.state.loading, true);
});
