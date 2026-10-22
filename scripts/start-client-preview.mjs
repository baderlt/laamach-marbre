import { spawn, spawnSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { createConnection } from 'node:net';
import { delimiter, dirname, resolve } from 'node:path';
import { homedir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { parseEnv } from 'node:util';

const root = fileURLToPath(new URL('../', import.meta.url));
const api = 'http://127.0.0.1:8101';
const site = 'http://127.0.0.1:3105';
const children = [];
let stopping = false;

function stop(code = 0) {
  if (stopping) return;
  stopping = true;
  for (const child of children) {
    if (child.exitCode !== null || !child.pid) continue;
    if (process.platform === 'win32') spawnSync('taskkill.exe', ['/PID', String(child.pid), '/T', '/F'], { windowsHide: true, stdio: 'ignore', timeout: 5000 });
    else child.kill('SIGTERM');
  }
  process.exit(code);
}
process.once('SIGINT', () => stop());
process.once('SIGTERM', () => stop());

function start(command, args, cwd, env) {
  const child = spawn(command, args, { cwd, env, stdio: 'inherit', windowsHide: true });
  children.push(child);
  child.once('error', error => { console.error(`Could not start ${command}: ${error.message}`); stop(1); });
  child.once('exit', code => { if (!stopping) { console.error(`Preview service stopped (${code ?? 'signal'}).`); stop(code || 1); } });
}
function listening(port) {
  return new Promise(resolve => {
    const socket = createConnection({ host: '127.0.0.1', port });
    const done = value => { socket.destroy(); resolve(value); };
    socket.setTimeout(1000);
    socket.once('connect', () => done(true));
    socket.once('error', () => done(false));
    socket.once('timeout', () => done(false));
  });
}
async function json(url) {
  const response = await fetch(url, { signal: AbortSignal.timeout(8000), redirect: 'error' });
  if (!response.ok) throw Error(`${url} returned ${response.status}`);
  return response.json();
}
async function apiReady() {
  try { const health = await json(`${api}/api/v1/health`); return health.application === 'laamach-marbre' && health.status === 'ok'; }
  catch { return false; }
}
async function waitUntil(check, label) {
  const deadline = Date.now() + 45000;
  while (!stopping && Date.now() < deadline) {
    if (await check()) return;
    await new Promise(resolve => setTimeout(resolve, 300));
  }
  throw Error(`${label} did not become ready. Check MySQL and the local environment files.`);
}

try {
  if (Number(process.versions.node.split('.')[0]) !== 24) {
    const candidates = [process.env.LAAMACH_NODE_BINARY, process.env.npm_node_execpath, resolve(homedir(), '.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin', process.platform === 'win32' ? 'node.exe' : 'node')];
    const runtime = candidates.find(binary => binary && existsSync(binary) && /^v24\./.test(spawnSync(binary, ['--version'], { encoding: 'utf8', windowsHide: true, timeout: 3000 }).stdout || ''));
    if (!runtime) throw Error('Use Node.js 24 or set LAAMACH_NODE_BINARY to its executable.');
    console.log('Using the installed Node.js 24 runtime for the client preview.');
    const runner = spawn(runtime, [fileURLToPath(import.meta.url)], { cwd: root, stdio: 'inherit', windowsHide: true, env: { ...process.env, PATH: `${dirname(runtime)}${delimiter}${process.env.PATH || ''}` } });
    children.push(runner);
    runner.once('error', error => { console.error(error.message); stop(1); });
    runner.once('exit', code => stop(code ?? 1));
  } else {
  const previewFile = resolve(root, 'apps/api/.env.client-preview');
  if (!existsSync(previewFile)) throw Error('Configure apps/api/.env.client-preview first; this command never creates or resets database records.');
  const previewSettings = parseEnv(readFileSync(previewFile, 'utf8'));
  if (previewSettings.APP_ENV !== 'testing' || previewSettings.DB_CONNECTION !== 'mysql'
    || previewSettings.DB_HOST !== '127.0.0.1' || previewSettings.DB_PORT !== '3306'
    || previewSettings.DB_DATABASE !== 'laamach_marbre_test' || !previewSettings.DB_USERNAME
    || !previewSettings.DB_PASSWORD || previewSettings.DB_URL || previewSettings.DB_SOCKET) {
    throw Error('The client-preview environment must explicitly target local MySQL port 3306 and laamach_marbre_test.');
  }
  const localPhp = 'C:/wamp64/bin/php/php8.3.6/php.exe';
  const php = process.env.LAAMACH_PHP_BINARY || (existsSync(localPhp) ? localPhp : 'php');
  const env = { ...process.env, ...previewSettings, DB_URL: '', DB_SOCKET: '' };
  for (const name of ['R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY']) {
    if (!process.env[name]?.trim()) throw Error('Client-preview runtime R2 credentials are missing. Load the ignored private credential file.');
    env[name] = process.env[name];
  }
  for (const name of Object.keys(env)) {
    if (name.startsWith('R2_MIGRATION_')) delete env[name];
  }
  if (Object.keys(previewSettings).some(name => name.startsWith('R2_MIGRATION_'))) {
    throw Error('Client-preview environment must not contain migration credentials.');
  }
  if (env.R2_PRIVATE_BUCKET !== 'laamach-private' || env.R2_PUBLISHED_BUCKET !== 'laamach-published'
    || env.R2_PUBLISHED_URL !== 'https://media.laamachmarbre.com'
    || (env.R2_ENDPOINT || '').replace(/\/$/, '') !== 'https://62b12e721a034df158ec37079d23d610.r2.cloudflarestorage.com') {
    throw Error('Client-preview R2 endpoint, buckets or published URL are invalid.');
  }
  if (existsSync(resolve(root, 'apps/api/bootstrap/cache/config.php'))) {
    throw Error('Client-preview Laravel configuration is cached; remove the stale cache before launching.');
  }
  delete env.LARAVEL_STORAGE_PATH;
  const ini = resolve(root, '.tools/php.ini');
  if (existsSync(ini)) env.PHPRC = ini;
  const verify = spawnSync(php, ['-r', `require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->loadEnvironmentFrom('.env.client-preview'); $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); App\\Support\\TestDatabaseGuard::assertPreviewTarget($app->environment(), config('database')); foreach (['r2_private' => 'laamach-private', 'r2_published' => 'laamach-published'] as $disk => $bucket) { $config = config('filesystems.disks.'.$disk); if (($config['bucket'] ?? null) !== $bucket || ($config['endpoint'] ?? null) !== getenv('R2_ENDPOINT') || !hash_equals(getenv('R2_ACCESS_KEY_ID'), $config['key'] ?? '') || !hash_equals(getenv('R2_SECRET_ACCESS_KEY'), $config['secret'] ?? '')) { throw new RuntimeException('Client-preview R2 disk configuration mismatch.'); } } if (config('filesystems.disks.r2_published.url') !== 'https://media.laamachmarbre.com') { throw new RuntimeException('Client-preview published URL mismatch.'); } foreach (['r2_migration_private', 'r2_migration_published'] as $disk) { if (config('filesystems.disks.'.$disk.'.key') || config('filesystems.disks.'.$disk.'.secret')) { throw new RuntimeException('Migration credentials reached the client preview.'); } }`], {
    cwd: resolve(root, 'apps/api'), env, windowsHide: true, encoding: 'utf8', timeout: 10000,
  });
  if (verify.status !== 0) throw Error('Client-preview MySQL identity or R2 disk configuration check failed; API and website were not started.');
  if (await listening(8101)) {
    throw Error('Port 8101 is already occupied; its database identity cannot be verified by this launcher.');
  } else {
    start(php, ['artisan', 'serve', '--env=testing', '--host=127.0.0.1', '--port=8101', '--no-reload'], resolve(root, 'apps/api'), env);
    await waitUntil(apiReady, 'The client-preview API');
  }
  if (await listening(3105)) {
    throw Error('Port 3105 is already occupied; its API connection cannot be verified by this launcher.');
  } else {
    const webEnv = { ...process.env, SITE_URL: site, LARAVEL_API_URL: api, LAAMACH_PROVISIONAL_PREVIEW: '1', NEXT_DIST_DIR: '.next-client-preview' };
    for (const name of Object.keys(webEnv)) {
      if (name.startsWith('R2_')) delete webEnv[name];
    }
    start(process.execPath, [resolve(root, 'node_modules/next/dist/bin/next'), 'dev', '--webpack', '--hostname', '127.0.0.1', '--port', '3105'], resolve(root, 'apps/web'), webEnv);
    await waitUntil(() => listening(3105), 'The client-preview website');
  }
  const [products, projects] = await Promise.all([json(`${api}/api/v1/products?locale=fr&per_page=1`), json(`${api}/api/v1/projects?locale=fr&per_page=1`)]);
  console.log(`\nClient preview: ${products.meta.total} published products, ${projects.meta.total} published project collections.`);
  console.log(`Website: ${site}/fr  |  ${site}/ar\nAdmin: ${api}/admin\nThis command does not seed, migrate or copy any data.`);
  if (children.length) console.log('Keep this terminal open. Ctrl+C stops only the services started by this command.');
  }
} catch (error) {
  console.error(error instanceof Error ? error.message : 'Could not start the client preview.');
  stop(1);
}
