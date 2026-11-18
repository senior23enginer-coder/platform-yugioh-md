const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const sourceRoot = path.join(root, 'torneos_yugioh/assets/source');
const manifest = JSON.parse(fs.readFileSync(path.join(sourceRoot, 'manifest.json'), 'utf8'));
const check = process.argv.includes('--check');
for (const [kind, target, marker] of [
  ['javascript', 'app.js', '// @runtime-start\n'],
  ['styles', 'app.css', '/* @runtime-start */\n']
]) {
  const seen = new Set();
  const result = manifest[kind].map(entry => {
    if (seen.has(entry.file)) throw new Error('Duplicate source: ' + entry.file);
    seen.add(entry.file);
    const filename = path.resolve(sourceRoot, entry.file);
    if (!filename.startsWith(sourceRoot + path.sep)) throw new Error('Source outside manifest root');
    const text = fs.readFileSync(filename, 'utf8');
    const offset = text.indexOf(marker);
    if (offset < 0) throw new Error('Missing editing header: ' + entry.file);
    return text.slice(offset + marker.length);
  }).join('');
  const output = path.join(sourceRoot, '..', target);
  if (check && !fs.readFileSync(output).equals(Buffer.from(result))) throw new Error('Stale bundle: ' + target);
  if (!check) fs.writeFileSync(output, result);
  console.log(`${check ? 'Verified' : 'Built'} ${target}: ${manifest[kind].length} source fragments`);
}
