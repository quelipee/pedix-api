import assert from 'node:assert/strict';
import test from 'node:test';
import { formatMoneyInput, parseMoneyInput } from './money.ts';

test('formats whole reais with two decimal places', () => {
    assert.deepEqual(formatMoneyInput('50'), {
        display: '50,00',
        decimal: '50.00',
    });
});

test('accepts centavos and formatted Brazilian values', () => {
    assert.deepEqual(formatMoneyInput('50,5'), {
        display: '50,50',
        decimal: '50.50',
    });
    assert.deepEqual(formatMoneyInput('1.000,00'), {
        display: '1.000,00',
        decimal: '1000.00',
    });
});

test('keeps invalid amounts out of the formatted value', () => {
    assert.equal(parseMoneyInput('50,999'), null);
    assert.equal(formatMoneyInput('abc'), null);
});
