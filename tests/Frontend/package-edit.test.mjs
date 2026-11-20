import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import vm from 'node:vm';

const rendered = JSON.parse(execFileSync('php', ['-r', `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
request()->setLaravelSession(session()->driver());
$source = str_replace(['<x-app-layout>', '</x-app-layout>'], '', file_get_contents('resources/views/adminSide/packages/edit.blade.php'));
$result = [];
foreach ([[0, null], [10000, null], [0, 'fixed'], [10000, 'percentage']] as [$price, $oldType]) {
    session()->put('_old_input', $oldType ? ['comm_type' => $oldType, 'price_display' => '125.50', 'commission_display' => '2.50'] : []);
    $package = new App\\Models\\RefCodePackage([
        'name' => 'Test Package', 'ref_code' => 'TEST', 'price_mode' => 'MONTHLY',
        'price' => $price, 'commission_rate' => $price ? 0 : 500,
        'base_lease' => 10, 'max_lease_limit' => 20, 'extra_lease_price' => 100,
    ]);
    $package->id = '01TESTPACKAGE';
    $result[] = Illuminate\\Support\\Facades\\Blade::render($source, compact('package'));
}
echo json_encode($result);
`], {
    encoding: 'utf8', cwd: new URL('../../', import.meta.url),
    env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', CACHE_STORE: 'array', SESSION_DRIVER: 'array' },
}));

function input(html, name) {
    return html.match(new RegExp(`<input\\b[^>]*\\bname="${name}"[^>]*>`))[0];
}

test('edit form enables only the stored billing model and submits as PUT', () => {
    for (const [index, fixed] of [[0, false], [1, true]]) {
        assert.equal(/\bdisabled(?:\s|>)/.test(input(rendered[index], 'commission_display')), fixed);
        assert.equal(/\bdisabled(?:\s|>)/.test(input(rendered[index], 'price_display')), !fixed);
        assert.match(input(rendered[index], '_method'), /value="PUT"/);
    }
});

test('validation redirects restore the selected billing model and entered amounts', () => {
    for (const [index, fixed] of [[2, true], [3, false]]) {
        assert.equal(/\bdisabled(?:\s|>)/.test(input(rendered[index], 'commission_display')), fixed);
        assert.equal(/\bdisabled(?:\s|>)/.test(input(rendered[index], 'price_display')), !fixed);
        assert.match(rendered[index], new RegExp(`value="${fixed ? 'fixed' : 'percentage'}" checked`));
        assert.match(input(rendered[index], fixed ? 'price_display' : 'commission_display'), new RegExp(`value="${fixed ? '125.50' : '2.50'}"`));
    }
});

test('switching billing models enables the selected amount for submission in both directions', () => {
    const radios = ['percentage', 'fixed'].map(value => ({
        value, addEventListener(event, handler) { this.change = handler; },
    }));
    const fields = {
        commission_display: { disabled: false, value: '5' },
        price_display: { disabled: true, value: '0' },
        section_percentage: { classList: new Set() },
        section_fixed: { classList: new Set(['hidden']) },
    };
    for (const id of ['section_percentage', 'section_fixed']) {
        fields[id].classList.remove = fields[id].classList.delete;
    }
    const script = rendered[0].match(/<script>([\s\S]*?)<\/script>/)[1];
    vm.runInNewContext(script, { document: {
        querySelectorAll: () => radios, getElementById: id => fields[id],
    } });

    for (const radio of [radios[1], radios[0], radios[1]]) {
        radio.change.call(radio);
        const fixed = radio.value === 'fixed';
        assert.equal(fields.commission_display.disabled, fixed);
        assert.equal(fields.price_display.disabled, !fixed);
        assert.equal(fields.section_percentage.classList.has('hidden'), fixed);
        assert.equal(fields.section_fixed.classList.has('hidden'), !fixed);
        assert.equal(fields[fixed ? 'commission_display' : 'price_display'].value, '');
    }
});
