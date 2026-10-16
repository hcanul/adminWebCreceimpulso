import test from 'node:test';
import assert from 'node:assert/strict';
import {estimateCredit,sumEstimates} from '../src/domain/credit-calculation.ts';
test('el estándar Cobre conserva 16 cuotas, interés y garantía',()=>{
 assert.deepEqual(estimateCredit('10000','80.0000'),{principal:'10000.00',weekly_payment:'800.00',total_repayment:'12800.00',interest:'2800.00',guarantee:'1000.00',net_delivery:'9000.00'});
});
test('tasas compartidas con montos individuales distintos',()=>{
 const result=sumEstimates(['10000','8000','5000'].map(amount=>estimateCredit(amount,'80')));
 assert.equal(result.principal,'23000.00');assert.equal(result.weekly_payment,'1840.00');assert.equal(result.total_repayment,'29440.00');
 assert.equal(estimateCredit('10000','75').weekly_payment,'750.00');assert.equal(estimateCredit('10000','70').weekly_payment,'700.00');
});
test('redondeo a centavos y rechazo de importes ambiguos',()=>{
 assert.equal(estimateCredit('1234.56','80').weekly_payment,'98.76');assert.equal(estimateCredit('1234.57','80').weekly_payment,'98.77');
 for(const amount of ['0','-100','10,000','1e4','100.001']) assert.throws(()=>estimateCredit(amount,'80'));
 assert.throws(()=>estimateCredit('10000','0.15'));
});
