const fs = require('node:fs');
const prettier = require('prettier');

// The PHP test first verifies the original fixture against its unchanged fingerprint.
// Canonical formatting is applied to both sides; original contracts are never rewritten.
(async () => {
  const cases = JSON.parse(fs.readFileSync(0, 'utf8'));
  const options = { ...(await prettier.resolveConfig('torneos_yugioh/src/Domain/Matches/DuelSequence.php')), filepath: 'contract.php' };
  async function canonical(source) {
    let current = '<?php\n' + source;
    for (let pass = 0; pass < 4; pass++) {
      const formatted = await prettier.format(current, options);
      if (formatted === current) return formatted;
      current = formatted;
    }
    throw Error('Canonical PHP formatting did not converge');
  }
  for (const item of cases) {
    const original = await canonical(item.original);
    const current = await canonical(item.current);
    if (original !== current) {
      let offset = 0;
      while (original[offset] === current[offset] && offset < original.length) offset++;
      throw Error('Algorithm differs after canonical formatting: ' + item.name + '\nOriginal: ' + JSON.stringify(original.slice(Math.max(0, offset - 60), offset + 180)) + '\nCurrent: ' + JSON.stringify(current.slice(Math.max(0, offset - 60), offset + 180)));
    }
  }
  console.log('PASS ' + cases.length + ' original/migrated algorithm comparisons; original fingerprints preserved.');
})().catch(error => { console.error(error.message); process.exitCode = 1; });
