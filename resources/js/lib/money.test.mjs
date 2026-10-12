import assert from 'node:assert/strict';
import test from 'node:test';
import { formatMoneyInput, parseMoneyInput } from './money.ts';

void test('formats whole reais with two decimal places', async () => {
    assert.deepEqual(formatMoneyInput('50'), {
        display: '50,00',
        decimal: '50.00',
    });
});

void test('accepts centavos and formatted Brazilian values', async () => {
    assert.deepEqual(formatMoneyInput('50,5'), {
        display: '50,50',
        decimal: '50.50',
    });
    assert.deepEqual(formatMoneyInput('1.000,00'), {
        display: '1.000,00',
        decimal: '1000.00',
    });
});

void test('keeps invalid amounts out of the formatted value', async () => {
    assert.equal(parseMoneyInput('50,999'), null);
    assert.equal(formatMoneyInput('abc'), null);
});
