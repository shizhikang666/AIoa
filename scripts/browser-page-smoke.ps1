param(
    [Parameter(Mandatory = $true)][string]$TargetPath,
    [string]$FrontendBaseUrl = 'http://127.0.0.1:83',
    [string]$EnvPath = (Join-Path (Resolve-Path (Join-Path $PSScriptRoot '..')) '.env'),
    [string]$ChromePath = 'C:\Program Files\Google\Chrome\Application\chrome.exe',
    [int]$InitialWaitMs = 18000,
    [int]$AfterClickWaitMs = 10000,
    [int]$MinRows = 0,
    [string]$ForbiddenPathPattern = '(/|^)(add|edit|delete|del|complete|upload|doLogin|doLogout|start|approve|reject|cancel|send|grant|reset|enable|disable|revoke|save)(\b|\?|/)',
    [switch]$ClickFirstTableLink,
    [switch]$AllowMissingTableLink
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $ProjectRoot

function Get-EnvMap {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        throw "Missing local env file: $Path"
    }

    $map = @{}
    foreach ($line in Get-Content -LiteralPath $Path) {
        $trimmed = $line.Trim()
        if ($trimmed -eq '' -or $trimmed.StartsWith('#')) {
            continue
        }

        $parts = $trimmed -split '=', 2
        if ($parts.Count -ne 2) {
            continue
        }

        $key = $parts[0].Trim()
        $value = $parts[1].Trim()
        if (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'"))) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        $map[$key] = $value
    }

    return $map
}

function Get-EnvValue {
    param(
        [Parameter(Mandatory = $true)][hashtable]$EnvMap,
        [Parameter(Mandatory = $true)][string]$Key,
        [string]$Default = ''
    )

    if ($EnvMap.ContainsKey($Key) -and [string]$EnvMap[$Key] -ne '') {
        return [string]$EnvMap[$Key]
    }

    return $Default
}

if (-not (Test-Path -LiteralPath $ChromePath)) {
    throw "Chrome executable not found: $ChromePath"
}

$envMap = Get-EnvMap -Path $EnvPath
$account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
if ($account -eq '') {
    throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env'
}

$safeAccount = $account.Replace("'", "\'")
$tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_BROWSER_PAGE_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = (& php -r $tokenCode).Trim()
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$debugPort = Get-Random -Minimum 9230 -Maximum 9299
$env:CODEX_BROWSER_TOKEN = $token
$env:CODEX_BROWSER_FRONTEND = $FrontendBaseUrl.TrimEnd('/')
$env:CODEX_BROWSER_TARGET_PATH = $TargetPath
$env:CODEX_BROWSER_CHROME = $ChromePath
$env:CODEX_BROWSER_DEBUG_PORT = [string]$debugPort
$env:CODEX_BROWSER_INITIAL_WAIT_MS = [string]$InitialWaitMs
$env:CODEX_BROWSER_AFTER_CLICK_WAIT_MS = [string]$AfterClickWaitMs
$env:CODEX_BROWSER_MIN_ROWS = [string]$MinRows
$env:CODEX_BROWSER_CLICK_FIRST_TABLE_LINK = if ($ClickFirstTableLink) { '1' } else { '0' }
$env:CODEX_BROWSER_ALLOW_MISSING_TABLE_LINK = if ($AllowMissingTableLink) { '1' } else { '0' }
$env:CODEX_BROWSER_FORBIDDEN_PATH_PATTERN = $ForbiddenPathPattern

$browserSmokeMutex = New-Object System.Threading.Mutex($false, 'CodexOaBrowserPageSmoke')
if (-not $browserSmokeMutex.WaitOne([TimeSpan]::FromMinutes(3))) {
    throw 'Timed out waiting for browser smoke lock'
}

$nodeScript = @'
import { spawn, execFileSync } from 'node:child_process';
import http from 'node:http';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';

const token = process.env.CODEX_BROWSER_TOKEN;
const frontendBase = process.env.CODEX_BROWSER_FRONTEND;
const targetPath = process.env.CODEX_BROWSER_TARGET_PATH || '/';
const chromePath = process.env.CODEX_BROWSER_CHROME;
const port = Number(process.env.CODEX_BROWSER_DEBUG_PORT || '9230');
const initialWaitMs = Number(process.env.CODEX_BROWSER_INITIAL_WAIT_MS || '18000');
const afterClickWaitMs = Number(process.env.CODEX_BROWSER_AFTER_CLICK_WAIT_MS || '10000');
const minRows = Number(process.env.CODEX_BROWSER_MIN_ROWS || '0');
const clickFirstTableLink = process.env.CODEX_BROWSER_CLICK_FIRST_TABLE_LINK === '1';
const allowMissingTableLink = process.env.CODEX_BROWSER_ALLOW_MISSING_TABLE_LINK === '1';
const forbiddenPathPattern = process.env.CODEX_BROWSER_FORBIDDEN_PATH_PATTERN || '(/|^)(add|edit|delete|del|complete|upload|doLogin|doLogout|start|approve|reject|cancel|send|grant|reset|enable|disable|revoke|save)(\\b|\\?|/)';
const apiBase = `${frontendBase}/api`;
const targetUrl = `${frontendBase}${targetPath.startsWith('/') ? targetPath : '/' + targetPath}`;

if (!token || !frontendBase || !chromePath) {
  throw new Error('missing required browser smoke environment');
}

function api(path, method = 'GET') {
  const args = ['-sS', '-X', method, `${apiBase}${path}`, '-H', `Authorization: Bearer ${token}`];
  if (method === 'POST') {
    args.push('-H', 'Content-Type: application/json', '--data-binary', '{}');
  }
  const raw = execFileSync('curl.exe', args, { encoding: 'utf8' }).replace(/^\uFEFF/, '');
  const json = JSON.parse(raw);
  if (json.code !== 200) {
    throw new Error(`${path} returned code=${json.code}`);
  }
  return json.data;
}

const cache = {
  TOKEN: token,
  USER_INFO: api('/auth/b/getLoginUser'),
  MENU: api('/sys/userCenter/loginMenu'),
  SYS_CONFIG: api('/sys/sysConfig/detail'),
  SYS_USER_PROCESS_CONFIG: api('/sys/userCenter/process/config', 'POST'),
  DICT_TYPE_TREE_DATA: api('/dev/dict/tree')
};
const tenantId = cache.USER_INFO?.tenantId || cache.USER_INFO?.user?.TENANT_ID || cache.USER_INFO?.user?.tenantId || '';

function normalizeRoutePath(value) {
  if (!value) {
    return '';
  }
  const raw = String(value).split('?')[0].replace(/\/+$/g, '');
  return raw.startsWith('/') ? raw : `/${raw}`;
}

function nodeMatchesTarget(node, target) {
  const pathValue = normalizeRoutePath(node?.path);
  return pathValue === target || pathValue === `${target}/index` || `${pathValue}/index` === target;
}

function treeContainsTarget(node, target) {
  if (nodeMatchesTarget(node, target)) {
    return true;
  }
  const children = Array.isArray(node?.children) ? node.children : [];
  return children.some(child => treeContainsTarget(child, target));
}

const normalizedTargetPath = normalizeRoutePath(targetPath);
const targetModule = (Array.isArray(cache.MENU) ? cache.MENU : []).find(module => treeContainsTarget(module, normalizedTargetPath));
const selectedModuleId = targetModule?.id || cache.MENU?.[0]?.id || '';

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function requestJson(url, method = 'GET') {
  return new Promise((resolve, reject) => {
    const req = http.request(url, { method }, res => {
      let data = '';
      res.setEncoding('utf8');
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        if (res.statusCode < 200 || res.statusCode >= 300) {
          reject(new Error(`${method} ${url} status ${res.statusCode}: ${data}`));
          return;
        }
        try {
          resolve(JSON.parse(data));
        } catch (error) {
          reject(error);
        }
      });
    });
    req.on('error', reject);
    req.end();
  });
}

async function waitFor(fn, timeoutMs, label) {
  const started = Date.now();
  let lastError;
  while (Date.now() - started < timeoutMs) {
    try {
      const value = await fn();
      if (value) {
        return value;
      }
    } catch (error) {
      lastError = error;
    }
    await sleep(250);
  }
  throw new Error(`${label} timed out${lastError ? ': ' + lastError.message : ''}`);
}

class Cdp {
  constructor(url) {
    this.url = url;
    this.id = 0;
    this.pending = new Map();
    this.handlers = [];
  }

  async open() {
    this.ws = new WebSocket(this.url);
    await new Promise((resolve, reject) => {
      const timer = setTimeout(() => reject(new Error('websocket open timeout')), 10000);
      this.ws.addEventListener('open', () => {
        clearTimeout(timer);
        resolve();
      }, { once: true });
      this.ws.addEventListener('error', () => {
        clearTimeout(timer);
        reject(new Error('websocket error'));
      }, { once: true });
    });

    this.ws.addEventListener('message', event => {
      const msg = JSON.parse(event.data);
      if (msg.id && this.pending.has(msg.id)) {
        const pending = this.pending.get(msg.id);
        this.pending.delete(msg.id);
        if (msg.error) {
          pending.reject(new Error(msg.error.message || 'CDP error'));
        } else {
          pending.resolve(msg.result);
        }
        return;
      }
      for (const handler of this.handlers) {
        handler(msg);
      }
    });
  }

  on(handler) {
    this.handlers.push(handler);
  }

  send(method, params = {}) {
    const id = ++this.id;
    this.ws.send(JSON.stringify({ id, method, params }));
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => {
        this.pending.delete(id);
        reject(new Error(`${method} timeout`));
      }, 30000);
      this.pending.set(id, {
        resolve: value => {
          clearTimeout(timer);
          resolve(value);
        },
        reject: error => {
          clearTimeout(timer);
          reject(error);
        }
      });
    });
  }

  close() {
    try {
      this.ws?.close();
    } catch {}
  }
}

function isBackendApi(url) {
  try {
    return new URL(url).pathname.startsWith('/api/');
  } catch {
    return false;
  }
}

function allowedConsole(text) {
  return text.startsWith('Warning: [ant-design-vue: Descriptions]')
    || text.startsWith('Warning: [ant-design-vue: Table] width must be a number when use resizable');
}

async function consoleArgToText(arg) {
  if (Object.prototype.hasOwnProperty.call(arg, 'value')) {
    if (typeof arg.value === 'string') {
      return arg.value;
    }
    try {
      return JSON.stringify(arg.value);
    } catch {
      return String(arg.value);
    }
  }
  if (arg.unserializableValue) {
    return String(arg.unserializableValue);
  }
  if (arg.objectId) {
    try {
      const value = await cdp.send('Runtime.callFunctionOn', {
        objectId: arg.objectId,
        returnByValue: true,
        awaitPromise: false,
        functionDeclaration: `function () {
          try {
            if (this instanceof Error) {
              return this.stack || this.message || String(this);
            }
            return JSON.stringify(this);
          } catch (error) {
            try {
              return String(this);
            } catch {
              return '<unprintable console object>';
            }
          }
        }`
      });
      const result = value?.result?.value;
      if (result !== undefined && result !== null && String(result) !== '{}') {
        return String(result);
      }
    } catch {
    }
  }
  if (arg.preview?.properties?.length) {
    const props = arg.preview.properties
      .slice(0, 8)
      .map(prop => `${prop.name}:${prop.value ?? prop.type ?? ''}`)
      .join(', ');
    return `${arg.description || arg.type || 'Object'}{${props}}`;
  }
  return arg.description || arg.className || arg.type || '';
}

function recordConsoleError(args) {
  const task = Promise.all(args.map(arg => consoleArgToText(arg)))
    .then(parts => {
      const text = parts.filter(part => part !== '').join(' ').slice(0, 800);
      if (!allowedConsole(text)) {
        consoleErrors.push(text);
      }
    })
    .catch(error => {
      const text = `console capture failed: ${error.message}`.slice(0, 800);
      if (!allowedConsole(text)) {
        consoleErrors.push(text);
      }
    });
  consoleCaptureTasks.push(task);
}

let forbiddenPattern;
try {
  forbiddenPattern = new RegExp(forbiddenPathPattern, 'i');
} catch (error) {
  throw new Error(`invalid forbidden path regex: ${error.message}`);
}
const apiRequests = [];
const apiResponses = [];
const failedLoads = [];
const consoleErrors = [];
const consoleCaptureTasks = [];
const pageErrors = [];
const forbidden = [];

const profileDir = await fs.mkdtemp(path.join(os.tmpdir(), 'codex-oa-browser-smoke-'));
const chrome = spawn(chromePath, [
  `--remote-debugging-port=${port}`,
  `--user-data-dir=${profileDir}`,
  '--headless=new',
  '--disable-gpu',
  '--no-first-run',
  '--no-default-browser-check',
  '--remote-allow-origins=*',
  'about:blank'
], { stdio: 'ignore' });

let cdp;

async function evalJson(expression) {
  const result = await cdp.send('Runtime.evaluate', {
    returnByValue: true,
    awaitPromise: true,
    expression: `(() => { try { return JSON.stringify((${expression})()); } catch (error) { return JSON.stringify({ __error: true, name: error.name, message: error.message, stack: error.stack, href: location.href, body: document.body?.innerText?.slice(0, 1000) || '' }); } })()`
  });
  if (result.exceptionDetails) {
    throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text || 'Runtime.evaluate exception');
  }
  const value = JSON.parse(result.result.value || 'null');
  if (value?.__error) {
    throw new Error(`${value.name}: ${value.message}\n${value.body}`);
  }
  return value;
}

try {
  await waitFor(() => requestJson(`http://127.0.0.1:${port}/json/version`), 20000, 'Chrome CDP');
  let target;
  try {
    target = await requestJson(`http://127.0.0.1:${port}/json/new?${encodeURIComponent('about:blank')}`, 'PUT');
  } catch {
    target = (await requestJson(`http://127.0.0.1:${port}/json`))[0];
  }

  cdp = new Cdp(target.webSocketDebuggerUrl);
  await cdp.open();
  cdp.on(msg => {
    if (msg.method === 'Network.requestWillBeSent') {
      const req = msg.params.request;
      if (isBackendApi(req.url)) {
        const row = { method: req.method, url: req.url.replace(/[?&]_=[0-9]+/g, '') };
        apiRequests.push(row);
        if (forbiddenPattern.test(new URL(req.url).pathname)) {
          forbidden.push(row);
        }
      }
    }

    if (msg.method === 'Network.responseReceived') {
      const res = msg.params.response;
      if (isBackendApi(res.url)) {
        apiResponses.push({ status: res.status, url: res.url.replace(/[?&]_=[0-9]+/g, '') });
      }
    }

    if (msg.method === 'Network.loadingFailed') {
      failedLoads.push({ requestId: msg.params.requestId, errorText: msg.params.errorText, canceled: msg.params.canceled });
    }

    if (msg.method === 'Runtime.consoleAPICalled' && ['error', 'assert'].includes(msg.params.type)) {
      recordConsoleError(msg.params.args);
    }

    if (msg.method === 'Log.entryAdded' && msg.params.entry.level === 'error') {
      const text = (msg.params.entry.text || '').slice(0, 300);
      if (!allowedConsole(text)) {
        pageErrors.push(text);
      }
    }
  });

  await cdp.send('Network.enable');
  await cdp.send('Runtime.enable');
  await cdp.send('Page.enable');
  await cdp.send('Log.enable');
  await cdp.send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });

  const inject = `try { const cache = ${JSON.stringify(cache)}; for (const [key, value] of Object.entries(cache)) localStorage.setItem(key, JSON.stringify(value)); localStorage.setItem('tenantId', JSON.stringify(${JSON.stringify(tenantId)})); localStorage.setItem('SNOWY_MENU_MODULE_ID', JSON.stringify(${JSON.stringify(selectedModuleId)})); localStorage.setItem('SNOWY_LAYOUT', JSON.stringify('classical')); } catch (error) { console.error(error); }`;
  await cdp.send('Page.addScriptToEvaluateOnNewDocument', { source: inject });
  await cdp.send('Page.navigate', { url: targetUrl });
  await sleep(initialWaitMs);

  const state = await evalJson(`() => { const visible = el => !!(el && (el.offsetWidth || el.offsetHeight || el.getClientRects().length)); const rows = Array.from(document.querySelectorAll('.ant-table-tbody tr')).filter(visible).length; const text = document.body ? document.body.innerText.slice(0, 1600) : ''; return { href: location.href, path: location.pathname, title: document.title, rows, is404: text.includes('404'), text }; }`);
  if (state.is404) {
    throw new Error(`target rendered 404 at ${state.path}`);
  }
  if (state.path.includes('/login')) {
    throw new Error(`redirected to login at ${state.path}`);
  }
  if (state.rows < minRows) {
    throw new Error(`expected at least ${minRows} table rows, saw ${state.rows}`);
  }

  let click = { clicked: false, candidateCount: 0 };
  if (clickFirstTableLink) {
    click = await evalJson(`() => { const visible = el => !!(el && (el.offsetWidth || el.offsetHeight || el.getClientRects().length)); const candidates = Array.from(document.querySelectorAll('.ant-table-tbody a')).filter(el => visible(el)); const target = candidates[0]; if (!target) return { clicked: false, candidateCount: 0 }; target.click(); return { clicked: true, candidateCount: candidates.length, label: (target.innerText || target.textContent || '').trim().slice(0, 80) }; }`);
    if (!click.clicked) {
      if (!allowMissingTableLink) {
        throw new Error('no visible table link found to click');
      }
      click.missingAllowed = true;
    } else {
      await sleep(afterClickWaitMs);
    }
  }

  const detail = await evalJson(`() => { const visible = el => !!(el && (el.offsetWidth || el.offsetHeight || el.getClientRects().length)); const drawerOpen = Array.from(document.querySelectorAll('.ant-drawer,.ant-modal')).some(visible); const text = document.body ? document.body.innerText.slice(0, 1600) : ''; return { drawerOpen, text }; }`);
  await Promise.allSettled(consoleCaptureTasks);
  const badStatuses = apiResponses.filter(row => row.status >= 400 && !/createSseConnect/.test(row.url));
  const nonCanceledFailures = failedLoads.filter(row => !row.canceled);
  const uniqueRequests = Array.from(new Map(apiRequests.map(row => [`${row.method} ${new URL(row.url).pathname}`, row])).values())
    .map(row => `${row.method} ${new URL(row.url).pathname}`)
    .slice(0, 100);

  const output = {
    ok: forbidden.length === 0 && badStatuses.length === 0 && nonCanceledFailures.length === 0 && consoleErrors.length === 0 && pageErrors.length === 0,
    target: targetUrl,
    state: { path: state.path, rows: state.rows, title: state.title },
    click,
    detail: { drawerOpen: detail.drawerOpen },
    forbiddenRequests: forbidden.map(row => `${row.method} ${new URL(row.url).pathname}`),
    badStatuses,
    failedLoads: nonCanceledFailures,
    consoleErrors,
    pageErrors,
    apiRequests: uniqueRequests
  };

  if (!output.ok) {
    console.log(JSON.stringify(output, null, 2));
    process.exit(1);
  }

  output.forbiddenRequests = 0;
  output.badApiStatuses = 0;
  output.consoleErrors = 0;
  console.log(JSON.stringify(output, null, 2));
} finally {
  try {
    await cdp?.send('Browser.close');
  } catch {}
  cdp?.close();
  try {
    chrome.kill();
  } catch {}
  await sleep(500);
  try {
    await fs.rm(profileDir, { recursive: true, force: true });
  } catch {}
}
'@

try {
    $nodeScript | node --input-type=module
    if ($LASTEXITCODE -ne 0) {
        throw "browser smoke failed with exit code $LASTEXITCODE"
    }
} finally {
    $env:CODEX_BROWSER_TOKEN = ''
    $env:CODEX_BROWSER_FRONTEND = ''
    $env:CODEX_BROWSER_TARGET_PATH = ''
    $env:CODEX_BROWSER_CHROME = ''
    $env:CODEX_BROWSER_DEBUG_PORT = ''
    $env:CODEX_BROWSER_INITIAL_WAIT_MS = ''
    $env:CODEX_BROWSER_AFTER_CLICK_WAIT_MS = ''
    $env:CODEX_BROWSER_MIN_ROWS = ''
    $env:CODEX_BROWSER_CLICK_FIRST_TABLE_LINK = ''
    $env:CODEX_BROWSER_ALLOW_MISSING_TABLE_LINK = ''
    $env:CODEX_BROWSER_FORBIDDEN_PATH_PATTERN = ''
    if ($null -ne $browserSmokeMutex) {
        try {
            $browserSmokeMutex.ReleaseMutex() | Out-Null
        } catch {
        }
        $browserSmokeMutex.Dispose()
    }
}
