import assert from 'node:assert/strict';
import test from 'node:test';

import { dataTableDefaults, dataTableSelector } from '../../resources/js/datatables-config.js';

test('all application tables use 10 rows by default with only the requested page sizes', () => {
  assert.equal(dataTableSelector, 'table.table:not([data-datatable="off"])');
  assert.equal(dataTableDefaults.pageLength, 5);
  assert.deepEqual(dataTableDefaults.lengthMenu, [5, 10, 25]);
});
