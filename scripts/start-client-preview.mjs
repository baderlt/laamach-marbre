import { spawn, spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { createConnection } from 'node:net';
import { delimiter, dirname, resolve } from 'node:path';
import { homedir } from 'node:os';
import { fileURLToPath } from 'node:url';

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
  if (!existsSync(resolve(root, 'apps/api/.env.testing'))) throw Error('Configure apps/api/.env.testing first; this command never creates or resets database records.');
  if (await listening(8101)) {
    if (!await apiReady()) throw Error('Port 8101 is occupied, but the LAAMACH MARBRE API is not ready.');
    console.log('Using the running client-preview API on port 8101.');
  } else {
    const localPhp = 'C:/wamp64/bin/php/php8.3.6/php.exe';
    const php = process.env.LAAMACH_PHP_BINARY || (existsSync(localPhp) ? localPhp : 'php');
    const env = { ...process.env };
    const ini = resolve(root, '.tools/php.ini');
    if (existsSync(ini)) env.PHPRC = ini;
    start(php, ['artisan', 'serve', '--env=testing', '--host=127.0.0.1', '--port=8101', '--no-reload'], resolve(root, 'apps/api'), env);
    await waitUntil(apiReady, 'The client-preview API');
  }
  if (await listening(3105)) {
    const response = await fetch(`${site}/fr`, { signal: AbortSignal.timeout(15000), redirect: 'error' });
    if (!response.ok || !(await response.text()).includes('data-backend-status="ok"')) throw Error('Port 3105 is occupied, but its website is not connected to the client-preview API.');
    console.log('Using the running client-preview website on port 3105.');
  } else {
    start(process.execPath, [resolve(root, 'node_modules/next/dist/bin/next'), 'dev', '--webpack', '--hostname', '127.0.0.1', '--port', '3105'], resolve(root, 'apps/web'), {
      ...process.env, SITE_URL: site, LARAVEL_API_URL: api, LAAMACH_PROVISIONAL_PREVIEW: '1', NEXT_DIST_DIR: '.next-client-preview',
    });
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
