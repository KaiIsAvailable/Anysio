import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { calculateUtilityTariff, utilityTariffSettings, utilityTariffManager } from '../../resources/js/utility-tariff-settings.js';

const { profiles } = JSON.parse(execFileSync('php', [fileURLToPath(new URL('../Fixtures/utility-tariffs.php', import.meta.url))], { encoding: 'utf8' }));

test('TNB changes the rate on every kWh above 1500, with retail waiver at 600', () => {
    const tnb = profiles.electricity_tnb_domestic;
    assert.equal(calculateUtilityTariff(tnb, 600), 266.58);
    assert.equal(calculateUtilityTariff(tnb, 601), 277.02);
    assert.equal(calculateUtilityTariff(tnb, 1500), 676.45);
    assert.equal(calculateUtilityTariff(tnb, 1501), 826.99);
});

test('Water progressively allocates usage to each block and applies minimum charges', () => {
    const water = profiles.water_selangor_domestic;
    for (const [usage, price] of [[0, 6.5], [5, 6.5], [20, 13], [35, 37.3], [40, 54.85], [20.5, 13.81]]) {
        assert.equal(calculateUtilityTariff(water, usage), price);
    }
    assert.equal(calculateUtilityTariff(profiles.water_sabah_domestic, 70), 76);
    assert.equal(calculateUtilityTariff(profiles.water_sarawak_domestic, 60), 40);
    assert.equal(calculateUtilityTariff(profiles.water_penang_domestic, 35), 32.5);
    assert.equal(calculateUtilityTariff(profiles.water_perak_domestic, 0), 7.5);
    assert.equal(calculateUtilityTariff(profiles.water_perak_domestic, 30), 25.8);
    assert.equal(calculateUtilityTariff(profiles.water_perak_domestic, 40), 37.95);
});

test('editing default official rates creates an account default draft without changing the reference', () => {
    const catalog = {
        official: { label: 'LAP individual', kind: 'official', is_default: true, profile_id: 'water_perak_domestic',
            calculation: 'blocks', minimum_policy: 'monthly', minimum_charge: '7.5', tiers: [{ limit: null, rate: '0.75' }] },
        custom: { label: 'Another tariff', kind: 'custom', is_default: false, tiers: [{ limit: null, rate: '1' }] },
    };
    const original = JSON.stringify(catalog);
    const editor = utilityTariffManager(catalog, '2026-11-15');
    assert.equal(editor.previewId, 'official');
    editor.copyReference('official');
    assert.equal(editor.fields.id, '');
    assert.equal(editor.fields.make_default, true);
    assert.equal(editor.officialMode, false);
    assert.equal(editor.fields.definition.minimum_policy, 'monthly');
    editor.fields.definition.tiers[0].rate = '0.95';
    assert.equal(JSON.stringify(catalog), original);
    editor.catalog.custom.is_default = true;
    editor.editTariff('custom');
    assert.equal(editor.fields.id, 'custom');
    assert.equal(editor.fields.make_default, true);
});

test('Bulk water uses its flat rate and account minimum once', () => {
    assert.equal(calculateUtilityTariff(profiles.water_selangor_bulk, 10), 173);
    assert.equal(calculateUtilityTariff(profiles.water_selangor_bulk, 100), 209);
    assert.equal(calculateUtilityTariff(profiles.water_johor_bulk, 20), 55);
});

test('Unverified schedules and invalid fields cannot silently produce a price', () => {
    assert.equal(calculateUtilityTariff(profiles.water_pahang_domestic, 30), null);
    for (const usage of ['', null, -1, 'abc']) assert.equal(calculateUtilityTariff(profiles.water_sabah_domestic, usage), null);
    const modal = utilityTariffSettings(profiles, 'water_selangor_domestic');
    modal.fields.tiers[1].limit = 5;
    assert.equal(calculateUtilityTariff(modal.fields, 40), null);
});

test('Switching profiles isolates edits and uses the saved schedule for the selected profile', () => {
    const modal = utilityTariffSettings(profiles, 'water_selangor_domestic');
    modal.fields.tiers[0].rate = 99;
    modal.editingTariff = true;
    modal.selectProfile('water_sabah_domestic');
    assert.equal(modal.fields.tiers.length, 5);
    assert.equal(modal.editingTariff, false);
    modal.selectProfile('water_selangor_domestic');
    assert.equal(modal.fields.tiers[0].rate, 0.65);
    modal.savingTariff = true;
    modal.selectProfile('water_sabah_domestic');
    assert.equal(modal.profileId, 'water_selangor_domestic');
});

test('Adding/removing tiers keeps the final block unlimited and switching to flat keeps one block', () => {
    const modal = utilityTariffSettings(profiles, 'water_selangor_domestic');
    modal.addTier();
    assert.equal(modal.fields.tiers.length, 4);
    assert.equal(modal.fields.tiers[2].limit, 55);
    assert.equal(modal.fields.tiers[3].limit, null);
    modal.removeTier(1);
    assert.equal(modal.fields.tiers.length, 3);
    assert.equal(modal.fields.tiers[2].limit, null);
    modal.setCalculation('flat');
    assert.equal(modal.fields.tiers.length, 1);
    assert.equal(modal.fields.tiers[0].limit, null);
});
