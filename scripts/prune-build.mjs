// After a build, delete assets that the manifest no longer uses, but only once they are a day old,
// so a page that was loaded just before the build can still fetch its files.
import { readFileSync, readdirSync, statSync, unlinkSync } from 'node:fs';
import { join } from 'node:path';

const dir = 'public/build';
const manifest = JSON.parse(readFileSync(join(dir, 'manifest.json'), 'utf8'));
const used = new Set();
for (const e of Object.values(manifest)) {
    used.add(e.file);
    (e.css || []).forEach(f => used.add(f));
    (e.assets || []).forEach(f => used.add(f));
}

let removed = 0;
for (const f of readdirSync(join(dir, 'assets'))) {
    const rel = `assets/${f}`;
    const path = join(dir, rel);
    if (!used.has(rel) && Date.now() - statSync(path).mtimeMs > 24 * 3600 * 1000) { unlinkSync(path); removed++; }
}
console.log(`pruned ${removed} old asset${removed === 1 ? '' : 's'}`);
