param(
    [Parameter(Mandatory = $true)][string]$Account,
    [Parameter(Mandatory = $true)][string]$TargetPath,
    [string]$Password = '123456',
    [string]$TenantId = '2018244380532912130',
    [string]$FrontendBaseUrl = 'https://oa.fucity.cn',
    [string]$ApiPrefix = '/backend',
    [string]$ChromePath = 'C:\Program Files\Google\Chrome\Application\chrome.exe',
    [int]$InitialWaitMs = 16000,
    [int]$AfterClickWaitMs = 8000,
    [int]$MinRows = 0,
    [string]$ForbiddenPathPattern = '(/|^)(add|edit|delete|del|complete|upload|doLogin|doLogout|start|approve|reject|cancel|send|grant|reset|enable|disable|revoke|save)(\b|\?|/)',
    [switch]$ClickFirstTableLink,
    [switch]$AllowMissingTableLink
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $ProjectRoot

if (-not (Test-Path -LiteralPath $ChromePath)) {
    throw "Chrome executable not found: $ChromePath"
}

function Get-FreeTcpPort {
    $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 0)
    try {
        $listener.Start()
        return [int]$listener.LocalEndpoint.Port
    } finally {
        $listener.Stop()
    }
}

$debugPort = Get-FreeTcpPort
$env:CODEX_BROWSER_ACCOUNT = $Account
$env:CODEX_BROWSER_PASSWORD = $Password
$env:CODEX_BROWSER_TENANT_ID = $TenantId
$env:CODEX_BROWSER_FRONTEND = $FrontendBaseUrl.TrimEnd('/')
$env:CODEX_BROWSER_API_PREFIX = '/' + $ApiPrefix.Trim('/')
$env:CODEX_BROWSER_TARGET_PATH = $TargetPath
$env:CODEX_BROWSER_CHROME = $ChromePath
$env:CODEX_BROWSER_DEBUG_PORT = [string]$debugPort
$env:CODEX_BROWSER_INITIAL_WAIT_MS = [string]$InitialWaitMs
$env:CODEX_BROWSER_AFTER_CLICK_WAIT_MS = [string]$AfterClickWaitMs
$env:CODEX_BROWSER_MIN_ROWS = [string]$MinRows
$env:CODEX_BROWSER_CLICK_FIRST_TABLE_LINK = if ($ClickFirstTableLink) { '1' } else { '0' }
$env:CODEX_BROWSER_ALLOW_MISSING_TABLE_LINK = if ($AllowMissingTableLink) { '1' } else { '0' }
$env:CODEX_BROWSER_FORBIDDEN_PATH_PATTERN = $ForbiddenPathPattern

$browserSmokeMutex = New-Object System.Threading.Mutex($false, 'CodexOaOnlineRoleBrowserSmoke')
if (-not $browserSmokeMutex.WaitOne([TimeSpan]::FromMinutes(3))) {
    throw 'Timed out waiting for browser smoke lock'
}

$nodeScript = @'
import { spawn } from 'node:child_process';
import http from 'node:http';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';

const account = process.env.CODEX_BROWSER_ACCOUNT;
const password = process.env.CODEX_BROWSER_PASSWORD;
const tenantIdInput = process.env.CODEX_BROWSER_TENANT_ID || '';
const frontendBase = process.env.CODEX_BROWSER_FRONTEND;
const apiPrefix = process.env.CODEX_BROWSER_API_PREFIX || '/backend';
const targetPath = process.env.CODEX_BROWSER_TARGET_PATH || '/';
const chromePath = process.env.CODEX_BROWSER_CHROME;
const port = Number(process.env.CODEX_BROWSER_DEBUG_PORT || '9330');
const initialWaitMs = Number(process.env.CODEX_BROWSER_INITIAL_WAIT_MS || '16000');
const afterClickWaitMs = Number(process.env.CODEX_BROWSER_AFTER_CLICK_WAIT_MS || '8000');
const minRows = Number(process.env.CODEX_BROWSER_MIN_ROWS || '0');
const clickFirstTableLink = process.env.CODEX_BROWSER_CLICK_FIRST_TABLE_LINK === '1';
const allowMissingTableLink = process.env.CODEX_BROWSER_ALLOW_MISSING_TABLE_LINK === '1';
const forbiddenPathPattern = process.env.CODEX_BROWSER_FORBIDDEN_PATH_PATTERN || '(/|^)(add|edit|delete|del|complete|upload|doLogin|doLogout|start|approve|reject|cancel|send|grant|reset|enable|disable|revoke|save)(\\b|\\?|/)';
const apiBase = `${frontendBase}${apiPrefix}`;
const targetUrl = `${frontendBase}${targetPath.startsWith('/') ? targetPath : '/' + targetPath}`;

if (!account || !password || !frontendBase || !chromePath) {
  throw new Error('missing required browser smoke environment');
}

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

async function api(pathname, options = {}) {
  const method = options.method || 'GET';
  const headers = {
    tenantId: tenantIdInput,
    ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}),
    ...(options.headers || {})
  };
  let body = options.body;
  if (body !== undefined && typeof body !== 'string') {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(body);
  }
  const response = await fetch(`${apiBase}${pathname}`, { method, headers, body });
  const text = await response.text();
  let json;
  try {
    json = JSON.parse(text.replace(/^\uFEFF/, ''));
  } catch {
    throw new Error(`${method} ${pathname} returned non-json status=${response.status}: ${text.slice(0, 300)}`);
  }
  if (json.code !== 200) {
    throw new Error(`${method} ${pathname} returned code=${json.code} msg=${json.msg || ''}`);
  }
  return json.data;
}

const token = await api('/auth/b/doLogin', {
  method: 'POST',
  body: {
    account,
    password,
    tenantId: tenantIdInput,
    device: 'CODEX_ONLINE_BROWSER_SMOKE'
  }
});

const cache = {
  TOKEN: token,
  USER_INFO: await api('/auth/b/getLoginUser', { token }),
  MENU: await api('/sys/userCenter/loginMenu', { token }),
  SYS_CONFIG: await api('/sys/sysConfig/detail', { token }),
  SYS_USER_PROCESS_CONFIG: await api('/sys/userCenter/process/config', { method: 'POST', token, body: {} }),
  DICT_TYPE_TREE_DATA: await api('/dev/dict/tree', { token })
};
const tenantId = cache.USER_INFO?.tenantId || cache.USER_INFO?.user?.TENANT_ID || tenantIdInput || '';

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
    return new URL(url).pathname.startsWith(`${apiPrefix}/`);
  } catch {
    return false;
  }
}

function normalizedApiPath(url) {
  const pathname = new URL(url).pathname;
  return pathname.startsWith(apiPrefix) ? pathname.slice(apiPrefix.length) || '/' : pathname;
}

function allowedConsole(text) {
  return text.startsWith('Warning: [ant-design-vue: Descriptions]')
    || text.startsWith('Warning: [ant-design-vue: Table] width must be a number when use resizable')
    || text.includes('ResizeObserver loop completed');
}

let cdp;
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
const requestMeta = new Map();
const responseCaptureTasks = [];
const appCodeErrors = [];
const failedLoads = [];
const consoleErrors = [];
const consoleCaptureTasks = [];
const pageErrors = [];
const forbidden = [];

const profileDir = await fs.mkdtemp(path.join(os.tmpdir(), 'codex-oa-online-browser-smoke-'));
let chromeStderr = '';
let chromeExit = null;
const chrome = spawn(chromePath, [
  `--remote-debugging-port=${port}`,
  `--user-data-dir=${profileDir}`,
  '--headless=new',
  '--disable-gpu',
  '--no-first-run',
  '--no-default-browser-check',
  '--remote-allow-origins=*',
  'about:blank'
], { stdio: ['ignore', 'ignore', 'pipe'] });
chrome.stderr?.on('data', chunk => {
  chromeStderr += chunk.toString();
  if (chromeStderr.length > 3000) {
    chromeStderr = chromeStderr.slice(-3000);
  }
});
chrome.once('exit', (code, signal) => {
  chromeExit = { code, signal };
});

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
  await waitFor(async () => {
    if (chromeExit) {
      const exitText = `Chrome exited code=${chromeExit.code ?? ''} signal=${chromeExit.signal ?? ''}`.trim();
      const stderrText = chromeStderr.trim();
      throw new Error(stderrText ? `${exitText}: ${stderrText}` : exitText);
    }
    return requestJson(`http://127.0.0.1:${port}/json/version`);
  }, 30000, 'Chrome CDP');
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
        requestMeta.set(msg.params.requestId, {
          method: req.method,
          path: normalizedApiPath(req.url),
          postData: req.postData || ''
        });
        if (forbiddenPattern.test(normalizedApiPath(req.url))) {
          forbidden.push(row);
        }
      }
    }

    if (msg.method === 'Network.responseReceived') {
      const res = msg.params.response;
      if (isBackendApi(res.url)) {
        const row = { status: res.status, url: res.url.replace(/[?&]_=[0-9]+/g, '') };
        apiResponses.push(row);
        const meta = requestMeta.get(msg.params.requestId) || { method: '', path: normalizedApiPath(res.url), postData: '' };
        const task = cdp.send('Network.getResponseBody', { requestId: msg.params.requestId })
          .then(bodyResult => {
            const bodyText = bodyResult?.body || '';
            if (!bodyText.trim().startsWith('{')) {
              return;
            }
            const json = JSON.parse(bodyText.replace(/^\uFEFF/, ''));
            if (json && Object.prototype.hasOwnProperty.call(json, 'code') && json.code !== 200) {
              appCodeErrors.push({
                method: meta.method,
                path: meta.path,
                code: json.code,
                msg: json.msg || json.message || '',
                postData: meta.postData.slice(0, 500)
              });
            }
          })
          .catch(() => {});
        responseCaptureTasks.push(task);
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

  const inject = `try {
    const cache = ${JSON.stringify(cache)};
    for (const [key, value] of Object.entries(cache)) localStorage.setItem(key, JSON.stringify(value));
    localStorage.setItem('tenantId', JSON.stringify(${JSON.stringify(tenantId)}));
    let snowySetting = {};
    try { snowySetting = JSON.parse(localStorage.getItem('SNOWY_SETTING')) || {}; } catch {}
    snowySetting.SNOWY_MENU_MODULE_ID = JSON.stringify(${JSON.stringify(selectedModuleId)});
    snowySetting.SNOWY_LAYOUT = JSON.stringify('classical');
    localStorage.setItem('SNOWY_SETTING', JSON.stringify(snowySetting));
  } catch (error) { console.error(error); }`;
  await cdp.send('Page.addScriptToEvaluateOnNewDocument', { source: inject });
  await cdp.send('Page.navigate', { url: targetUrl });
  await sleep(initialWaitMs);

  const state = await evalJson(`() => {
    const visible = el => !!(el && (el.offsetWidth || el.offsetHeight || el.getClientRects().length));
    const rows = Array.from(document.querySelectorAll('.ant-table-tbody tr')).filter(visible).length;
    const text = document.body ? document.body.innerText.slice(0, 1800) : '';
    return { href: location.href, path: location.pathname, title: document.title, rows, is404: text.includes('404'), isLogin: location.pathname.includes('/login'), text };
  }`);
  if (state.is404) {
    throw new Error(`target rendered 404 at ${state.path}`);
  }
  if (state.isLogin) {
    throw new Error(`redirected to login at ${state.path}`);
  }
  if (state.rows < minRows) {
    throw new Error(`expected at least ${minRows} table rows, saw ${state.rows}`);
  }

  let click = { clicked: false, candidateCount: 0 };
  if (clickFirstTableLink) {
    click = await evalJson(`() => {
      const visible = el => !!(el && (el.offsetWidth || el.offsetHeight || el.getClientRects().length));
      const candidates = Array.from(document.querySelectorAll('.ant-table-tbody a')).filter(el => visible(el));
      const target = candidates[0];
      if (!target) return { clicked: false, candidateCount: 0 };
      target.click();
      return { clicked: true, candidateCount: candidates.length, label: (target.innerText || target.textContent || '').trim().slice(0, 80) };
    }`);
    if (!click.clicked) {
      if (!allowMissingTableLink) {
        throw new Error('no visible table link found to click');
      }
      click.missingAllowed = true;
    } else {
      await sleep(afterClickWaitMs);
    }
  }

  const detail = await evalJson(`() => {
    const visible = el => !!(el && (el.offsetWidth || el.offsetHeight || el.getClientRects().length));
    const overlayOpen = Array.from(document.querySelectorAll('.ant-drawer,.ant-modal')).some(visible);
    const text = document.body ? document.body.innerText.slice(0, 1800) : '';
    return { overlayOpen, text };
  }`);
  await Promise.allSettled(responseCaptureTasks);
  await Promise.allSettled(consoleCaptureTasks);
  const badStatuses = apiResponses.filter(row => row.status >= 400);
  const nonCanceledFailures = failedLoads.filter(row => !row.canceled);
  const uniqueRequests = Array.from(new Map(apiRequests.map(row => [`${row.method} ${normalizedApiPath(row.url)}`, row])).values())
    .map(row => `${row.method} ${normalizedApiPath(row.url)}`)
    .slice(0, 100);

  const output = {
    ok: forbidden.length === 0 && badStatuses.length === 0 && appCodeErrors.length === 0 && nonCanceledFailures.length === 0 && consoleErrors.length === 0 && pageErrors.length === 0,
    account,
    target: targetUrl,
    state: { path: state.path, rows: state.rows, title: state.title },
    click,
    detail: { overlayOpen: detail.overlayOpen },
    forbiddenRequests: forbidden.map(row => `${row.method} ${normalizedApiPath(row.url)}`),
    badStatuses: badStatuses.map(row => ({ status: row.status, path: normalizedApiPath(row.url) })),
    appCodeErrors,
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
    $env:CODEX_BROWSER_ACCOUNT = ''
    $env:CODEX_BROWSER_PASSWORD = ''
    $env:CODEX_BROWSER_TENANT_ID = ''
    $env:CODEX_BROWSER_FRONTEND = ''
    $env:CODEX_BROWSER_API_PREFIX = ''
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
