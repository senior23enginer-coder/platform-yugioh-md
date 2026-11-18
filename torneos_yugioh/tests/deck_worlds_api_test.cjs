const assert = require('node:assert/strict');
const { create } = require('../assets/modules/deck-attributes/api.js');

(async () => {
  const calls = [];
  let token = 'first';
  let answer = { ok: true, records: [] };
  let statusOk = true;
  const client = create({
    fetch: async (url, options) => {
      calls.push({ url, options });
      return { ok: statusOk, json: async () => answer };
    },
    getSearch: () => '?public_season=2027&public_live=1&unrelated=secret',
    getToken: () => token,
  });
  assert.equal(calls.length, 0, 'composition must not issue requests');
  assert.deepEqual(await client.request({ deckId: 650 }), answer);
  const query = new URLSearchParams(calls[0].url.slice(1));
  assert.equal(query.get('api'), 'deck_attributes');
  assert.equal(query.get('deck_id'), '650');
  assert.equal(query.get('public_season'), '2027');
  assert.equal(query.get('public_live'), '1');
  assert.equal(query.has('unrelated'), false, 'only native public context is forwarded');
  assert.deepEqual(calls[0].options, { cache: 'no-store' });

  token = 'refreshed';
  const body = { deck_id: 650, attributes: ['water', 'light'], expected_revision: 2 };
  const before = JSON.stringify(body);
  await client.request({ body });
  assert.equal(calls[1].options.method, 'POST');
  assert.equal(calls[1].options.headers['X-CSRF-Token'], 'refreshed');
  assert.equal(calls[1].options.headers['Content-Type'], 'application/json');
  assert.deepEqual(JSON.parse(calls[1].options.body), body);
  assert.equal(JSON.stringify(body), before, 'transport must not mutate the command');

  statusOk = false;
  answer = { ok: false, message: 'Revision conflict' };
  await assert.rejects(client.request(), /Revision conflict/);
  statusOk = true;
  answer = { ok: false };
  await assert.rejects(client.request(), /No se pudieron consultar los mundos/);
  assert.throws(() => create({ fetch: () => {} }), /Faltan dependencias/);
  console.log('Deck worlds API: transport, public context, fresh CSRF and errors PASS.');
})().catch(error => { console.error(error); process.exitCode = 1; });
