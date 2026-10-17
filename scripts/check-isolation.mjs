import { execFileSync } from 'node:child_process';
import { readFileSync, lstatSync } from 'node:fs';
import { resolve, relative, isAbsolute } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const files = execFileSync('git', ['ls-files', '--cached', '--others', '--exclude-standard', '-z'], { cwd: root, encoding: 'utf8' }).split('\0').filter(Boolean);
const forbidden = /time[-_ ]stone|gos-cert\.org/i;
const legacyReference = /\bTS-[A-Z0-9]/;
const exceptions = new Set(['apps/api/tests/Unit/TestDatabaseGuardTest.php']);
const errors = [];
let intentional = 0;
for (const file of files) {
  const path = resolve(root, file);
  if (isAbsolute(relative(root, path)) || relative(root, path).startsWith('..')) errors.push(`Path outside repository: ${file}`);
  if (lstatSync(path).isSymbolicLink()) errors.push(`Source link is not allowed: ${file}`);
  if (/\.env(?:\.|$)/.test(file) && !file.endsWith('.env.example')) errors.push(`Private environment is tracked: ${file}`);
  const content = readFileSync(path, 'utf8');
  if (forbidden.test(content) || legacyReference.test(content)) {
    if (exceptions.has(file)) intentional++;
    else errors.push(`Unexpected reference identifier: ${file}`);
  }
}
if (errors.length) { console.error(errors.join('\n')); process.exitCode = 1; }
else console.log(`Isolation passed: ${files.length} source files; ${intentional} intentional forbidden-database fixture file; no external source links or tracked private environments.`);
