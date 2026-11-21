import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import vm from 'node:vm';

const html = execFileSync('php', ['-r', `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$source = file_get_contents('resources/views/adminSide/setting/index.blade.php');
preg_match('/<x-form\\.form\\b(?=[^>]*:preserve-settings-account="false")[\\s\\S]*?<\\/x-form\\.form>/', $source, $match);
echo Illuminate\\Support\\Facades\\Blade::render($match[0], [
    'settingsAccountId' => 'first-account',
    'settingsAccounts' => collect([
        (object) ['id' => 'first-account', 'name' => 'First', 'email' => 'first@example.test'],
        (object) ['id' => 'second-account', 'name' => 'Second', 'email' => 'second@example.test'],
    ]),
]);
`], { encoding: 'utf8', cwd: new URL('../../', import.meta.url), env: {
    ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', CACHE_STORE: 'array', SESSION_DRIVER: 'array',
} });
const handler = html.match(/@change="([\s\S]*?)"/)[1];
const form = { action: html.match(/\baction="([^"]+)"/)[1], dataset: { accountId: html.match(/data-account-id="([^"]+)"/)[1] } };

function selector(value, activeTab = 'payment') {
    const requests = [];
    const context = vm.createContext({ URL, activeTab, loading: false, $event: { detail: { value } }, $el: { closest: () => form },
        window: { location: { assign: target => requests.push(new URL(target)) } },
    });
    return { context, requests, change: () => vm.runInContext(handler, context) };
}

test('Account selection navigates with the chosen ID and current tab explicitly', () => {
    for (const tab of ['payment', 'lease', 'utility', 'fee-types', 'permissions', 'system']) {
        const { context, requests, change } = selector('second-account', tab);
        change();
        assert.equal(requests.length, 1);
        assert.equal(requests[0].searchParams.get('account_id'), 'second-account');
        assert.equal(requests[0].searchParams.get('tab'), tab);
        assert.equal(context.loading, true);
        change();
        assert.equal(requests.length, 1);
    }
});

test('Unchanged, empty and native change events do not reload Settings', () => {
    for (const value of ['first-account', '', null]) {
        const { requests, change } = selector(value);
        change();
        assert.equal(requests.length, 0);
    }
    const { context, requests, change } = selector('second-account');
    context.$event = {};
    change();
    assert.equal(requests.length, 0);
});
