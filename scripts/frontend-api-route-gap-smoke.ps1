param(
    [switch]$ShowMissing,
    [switch]$ShowCovered,
    [switch]$Json,
    [switch]$FailOnReadMissing
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $ProjectRoot

$nodeScript = @'
const fs = require('fs');
const path = require('path');
const childProcess = require('child_process');

const root = process.cwd();
const apiDir = path.join(root, 'snowy-admin-web', 'src', 'api');
const showMissing = process.env.CODEX_ROUTE_GAP_SHOW_MISSING === '1';
const showCovered = process.env.CODEX_ROUTE_GAP_SHOW_COVERED === '1';
const outputJson = process.env.CODEX_ROUTE_GAP_JSON === '1';
const failOnReadMissing = process.env.CODEX_ROUTE_GAP_FAIL_ON_READ === '1';

function walk(dir, out = []) {
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name);
    if (ent.isDirectory()) {
      walk(p, out);
    } else if (/\.js$/.test(ent.name)) {
      out.push(p);
    }
  }
  return out;
}

function rel(p) {
  return path.relative(root, p).replace(/\\/g, '/');
}

function stripComments(text) {
  const withoutHtml = text.replace(/<!--[\s\S]*?-->/g, match => match.replace(/[^\r\n]/g, ' '));
  let out = '';
  let state = 'code';
  let escaped = false;

  for (let i = 0; i < withoutHtml.length; i++) {
    const ch = withoutHtml[i];
    const next = withoutHtml[i + 1] || '';
    const code = ch.charCodeAt(0);

    if (state === 'line') {
      if (ch === '\r' || ch === '\n') {
        out += ch;
        state = 'code';
      } else {
        out += ' ';
      }
      continue;
    }

    if (state === 'block') {
      if (ch === '*' && next === '/') {
        out += '  ';
        i++;
        state = 'code';
      } else {
        out += (ch === '\r' || ch === '\n') ? ch : ' ';
      }
      continue;
    }

    if (state === 'single' || state === 'double' || state === 'template') {
      out += ch;
      if (escaped) {
        escaped = false;
      } else if (code === 92) {
        escaped = true;
      } else if ((state === 'single' && code === 39) || (state === 'double' && code === 34) || (state === 'template' && code === 96)) {
        state = 'code';
      }
      continue;
    }

    if (ch === '/' && next === '/') {
      out += '  ';
      i++;
      state = 'line';
    } else if (ch === '/' && next === '*') {
      out += '  ';
      i++;
      state = 'block';
    } else {
      out += ch;
      if (code === 39) {
        state = 'single';
      } else if (code === 34) {
        state = 'double';
      } else if (code === 96) {
        state = 'template';
      }
    }
  }

  return out;
}

function normalizePath(value) {
  return String(value || '')
    .replace(/\$\{[^}]*\}/g, '')
    .split('?')[0]
    .replace(/`/g, '')
    .replace(/\\/g, '/')
    .replace(/\/+/g, '/')
    .replace(/^\/+/, '')
    .replace(/\/+$/, '');
}

function parseRouteList() {
  const text = childProcess.execFileSync('php', ['think', 'route:list'], {
    encoding: 'utf8',
    maxBuffer: 20 * 1024 * 1024
  });
  const routes = new Map();

  for (const line of text.split(/\r?\n/)) {
    if (!line.startsWith('|')) {
      continue;
    }
    const columns = line.split('|').map(part => part.trim());
    if (columns.length < 5 || columns[1] === 'Rule' || columns[1] === '') {
      continue;
    }
    const rule = normalizePath(columns[1]);
    if (rule === '') {
      continue;
    }
    routes.set(rule, {
      path: rule,
      method: columns[3] || ''
    });
  }

  return routes;
}

function parseRequestPrefix(text) {
  const moduleTemplateMatch = text.match(/const\s+request\s*=\s*moduleRequest\(\s*`([^`]+)`\s*\)/);
  if (moduleTemplateMatch) {
    return moduleTemplateMatch[1];
  }
  const moduleQuoteMatch = text.match(/const\s+request\s*=\s*moduleRequest\(\s*['"]([^'"]+)['"]\s*\)/);
  if (moduleQuoteMatch) {
    return moduleQuoteMatch[1];
  }
  const templateMatch = text.match(/const\s+request\s*=\s*\([^)]*\)\s*=>\s*baseRequest\(\s*`([^`]+)`\s*\+\s*url/);
  if (templateMatch) {
    return templateMatch[1];
  }
  const quoteMatch = text.match(/const\s+request\s*=\s*\([^)]*\)\s*=>\s*baseRequest\(\s*['"]([^'"]+)['"]\s*\+\s*url/);
  if (quoteMatch) {
    return quoteMatch[1];
  }
  return '';
}

function findMatchingBrace(text, openIndex) {
  let depth = 0;
  let state = 'code';
  let escaped = false;

  for (let i = openIndex; i < text.length; i++) {
    const ch = text[i];
    const code = ch.charCodeAt(0);

    if (state === 'single' || state === 'double' || state === 'template') {
      if (escaped) {
        escaped = false;
      } else if (code === 92) {
        escaped = true;
      } else if ((state === 'single' && code === 39) || (state === 'double' && code === 34) || (state === 'template' && code === 96)) {
        state = 'code';
      }
      continue;
    }

    if (code === 39) {
      state = 'single';
      continue;
    }
    if (code === 34) {
      state = 'double';
      continue;
    }
    if (code === 96) {
      state = 'template';
      continue;
    }
    if (ch === '{') {
      depth++;
    } else if (ch === '}') {
      depth--;
      if (depth === 0) {
        return i;
      }
    }
  }

  return -1;
}

function parseExportedMethods(text) {
  const methods = [];
  const pattern = /(?:^|[,{;\n])\s*(?:async\s+)?([A-Za-z_$][\w$]*)\s*\([^)]*\)\s*\{/g;
  for (const match of text.matchAll(pattern)) {
    const name = match[1];
    if (['if', 'for', 'while', 'switch', 'function'].includes(name)) {
      continue;
    }
    const openIndex = match.index + match[0].lastIndexOf('{');
    const closeIndex = findMatchingBrace(text, openIndex);
    if (closeIndex === -1) {
      continue;
    }
    methods.push({
      name,
      body: text.slice(openIndex + 1, closeIndex)
    });
  }
  return methods;
}

function splitTopLevelArgs(argsText) {
  const args = [];
  let current = '';
  let depth = 0;
  let state = 'code';
  let escaped = false;

  for (let i = 0; i < argsText.length; i++) {
    const ch = argsText[i];
    const code = ch.charCodeAt(0);

    if (state === 'single' || state === 'double' || state === 'template') {
      current += ch;
      if (escaped) {
        escaped = false;
      } else if (code === 92) {
        escaped = true;
      } else if ((state === 'single' && code === 39) || (state === 'double' && code === 34) || (state === 'template' && code === 96)) {
        state = 'code';
      }
      continue;
    }

    if (code === 39) {
      current += ch;
      state = 'single';
    } else if (code === 34) {
      current += ch;
      state = 'double';
    } else if (code === 96) {
      current += ch;
      state = 'template';
    } else if (ch === '(' || ch === '[' || ch === '{') {
      current += ch;
      depth++;
    } else if (ch === ')' || ch === ']' || ch === '}') {
      current += ch;
      depth--;
    } else if (ch === ',' && depth === 0) {
      args.push(current.trim());
      current = '';
    } else {
      current += ch;
    }
  }

  if (current.trim() !== '') {
    args.push(current.trim());
  }
  return args;
}

function findFunctionCalls(text, name) {
  const calls = [];
  const pattern = new RegExp(`\\b${name}\\s*\\(`, 'g');
  for (const match of text.matchAll(pattern)) {
    const openIndex = match.index + match[0].lastIndexOf('(');
    let depth = 0;
    let state = 'code';
    let escaped = false;

    for (let i = openIndex; i < text.length; i++) {
      const ch = text[i];
      const code = ch.charCodeAt(0);

      if (state === 'single' || state === 'double' || state === 'template') {
        if (escaped) {
          escaped = false;
        } else if (code === 92) {
          escaped = true;
        } else if ((state === 'single' && code === 39) || (state === 'double' && code === 34) || (state === 'template' && code === 96)) {
          state = 'code';
        }
        continue;
      }

      if (code === 39) {
        state = 'single';
      } else if (code === 34) {
        state = 'double';
      } else if (code === 96) {
        state = 'template';
      } else if (ch === '(') {
        depth++;
      } else if (ch === ')') {
        depth--;
        if (depth === 0) {
          calls.push(text.slice(openIndex + 1, i));
          break;
        }
      }
    }
  }
  return calls;
}

function quotedValues(expression) {
  const values = [];
  const pattern = /(['"`])((?:\\.|(?!\1)[\s\S])*)\1/g;
  for (const match of expression.matchAll(pattern)) {
    values.push(match[2].replace(/\\(['"`\\])/g, '$1'));
  }
  return values;
}

function endpointValues(expression) {
  const questionIndex = expression.indexOf('?');
  const colonIndex = questionIndex === -1 ? -1 : expression.indexOf(':', questionIndex + 1);
  if (questionIndex !== -1 && colonIndex !== -1) {
    return quotedValues(expression.slice(questionIndex + 1, colonIndex))
      .concat(quotedValues(expression.slice(colonIndex + 1)));
  }
  return quotedValues(expression);
}

function inferHttpMethod(args) {
  const joined = args.slice(1).join(',');
  const methodMatch = joined.match(/['"]([A-Za-z]+)['"]/);
  if (!methodMatch) {
    return 'post';
  }
  return methodMatch[1].toLowerCase();
}

function isSideEffectLike(methodName, endpointPath, httpMethod) {
  const writeName = /(SubmitForm|Delete|Add|Edit|ApplyApproval|Complete|Cancel|Reject|Approve|Visibility|Amount|Deal|Repeal|Import|Export|Run|Stop|Send|Grant|Reset|Disable|Enable|MarkRead|Update|Upload|Password|Avatar|Signature|Workbench|Config|Sse|SSE|Stream)$/i;
  const writePath = /(^|\/)(add|edit|delete|del|cancel|repeal|approve|reject|start|send|grant|reset|disable|enable|upload|import|export|mark|run|stop|complete|success|batch|history|special|visibility|amount|deal|sse|stream)(\/|$)/i;
  return writeName.test(methodName) || writePath.test(endpointPath) || httpMethod !== 'get';
}

function collectEndpoints(file) {
  const raw = fs.readFileSync(file, 'utf8');
  const text = stripComments(raw);
  const prefix = parseRequestPrefix(text);
  const endpoints = [];

  for (const method of parseExportedMethods(text)) {
    for (const callText of findFunctionCalls(method.body, 'request')) {
      const args = splitTopLevelArgs(callText);
      if (args.length === 0) {
        continue;
      }
      const httpMethod = inferHttpMethod(args);
      const values = endpointValues(args[0]);
      if (values.length === 0) {
        endpoints.push({
          path: normalizePath(prefix),
          methodName: method.name,
          httpMethod,
          file: rel(file),
          dynamic: true
        });
        continue;
      }
      for (const value of values) {
        if (normalizePath(value) === '') {
          continue;
        }
        const endpointPath = normalizePath(`${prefix}${value}`);
        endpoints.push({
          path: endpointPath,
          methodName: method.name,
          httpMethod,
          file: rel(file),
          dynamic: /\$\{/.test(value)
        });
      }
    }

    for (const callText of findFunctionCalls(method.body, 'baseRequest')) {
      const args = splitTopLevelArgs(callText);
      if (args.length === 0) {
        continue;
      }
      const values = endpointValues(args[0]);
      const httpMethod = inferHttpMethod(args);
      for (const value of values) {
        if (normalizePath(value) === '') {
          continue;
        }
        endpoints.push({
          path: normalizePath(value),
          methodName: method.name,
          httpMethod,
          file: rel(file),
          dynamic: /\$\{/.test(value)
        });
      }
    }
  }

  return endpoints.filter(row => row.path !== '');
}

const routes = parseRouteList();
const allEndpoints = walk(apiDir).flatMap(collectEndpoints);
const uniqueMap = new Map();
for (const endpoint of allEndpoints) {
  const key = endpoint.path;
  const current = uniqueMap.get(key);
  if (!current) {
    uniqueMap.set(key, { ...endpoint, callers: [`${endpoint.file}#${endpoint.methodName}`] });
  } else {
    current.callers.push(`${endpoint.file}#${endpoint.methodName}`);
    if (current.httpMethod !== 'get' && endpoint.httpMethod === 'get') {
      current.httpMethod = 'get';
    }
  }
}

const uniqueEndpoints = [...uniqueMap.values()].sort((a, b) => a.path.localeCompare(b.path));
const covered = [];
const missingReadLike = [];
const missingSideEffectLike = [];

for (const endpoint of uniqueEndpoints) {
  if (routes.has(endpoint.path)) {
    covered.push(endpoint);
    continue;
  }
  if (isSideEffectLike(endpoint.methodName, endpoint.path, endpoint.httpMethod)) {
    missingSideEffectLike.push(endpoint);
  } else {
    missingReadLike.push(endpoint);
  }
}

const summary = {
  apiWrapperFiles: walk(apiDir).length,
  endpointReferences: allEndpoints.length,
  uniqueFrontendEndpoints: uniqueEndpoints.length,
  routePaths: routes.size,
  coveredByRoutePath: covered.length,
  missingByRoutePath: missingReadLike.length + missingSideEffectLike.length,
  missingReadLike: missingReadLike.length,
  missingSideEffectLike: missingSideEffectLike.length,
  covered: covered.map(row => row.path),
  missingReadLikeEndpoints: missingReadLike.map(row => ({ path: row.path, httpMethod: row.httpMethod, callers: row.callers })),
  missingSideEffectLikeEndpoints: missingSideEffectLike.map(row => ({ path: row.path, httpMethod: row.httpMethod, callers: row.callers }))
};

if (outputJson) {
  console.log(JSON.stringify(summary, null, 2));
} else {
  console.log('frontend API route gap summary');
  console.log(`api wrapper files: ${summary.apiWrapperFiles}`);
  console.log(`endpoint references: ${summary.endpointReferences}`);
  console.log(`unique frontend endpoints: ${summary.uniqueFrontendEndpoints}`);
  console.log(`route paths: ${summary.routePaths}`);
  console.log(`covered by route path: ${summary.coveredByRoutePath}`);
  console.log(`missing by route path: ${summary.missingByRoutePath}`);
  console.log(`missing read-like: ${summary.missingReadLike}`);
  console.log(`missing side-effect-like: ${summary.missingSideEffectLike}`);

  if (showMissing && missingReadLike.length > 0) {
    console.log('');
    console.log('missing read-like endpoints:');
    for (const row of missingReadLike) {
      console.log(`${row.path} <- ${row.callers.join(', ')}`);
    }
  }

  if (showMissing && missingSideEffectLike.length > 0) {
    console.log('');
    console.log('missing side-effect-like endpoints:');
    for (const row of missingSideEffectLike) {
      console.log(`${row.path} <- ${row.callers.join(', ')}`);
    }
  }

  if (showCovered && covered.length > 0) {
    console.log('');
    console.log('covered endpoints:');
    for (const row of covered) {
      console.log(`${row.path} <- ${row.callers.join(', ')}`);
    }
  }

  console.log('frontend API route gap scan completed');
}

if (failOnReadMissing && missingReadLike.length > 0) {
  process.exit(1);
}
'@

$env:CODEX_ROUTE_GAP_SHOW_MISSING = if ($ShowMissing) { '1' } else { '0' }
$env:CODEX_ROUTE_GAP_SHOW_COVERED = if ($ShowCovered) { '1' } else { '0' }
$env:CODEX_ROUTE_GAP_JSON = if ($Json) { '1' } else { '0' }
$env:CODEX_ROUTE_GAP_FAIL_ON_READ = if ($FailOnReadMissing) { '1' } else { '0' }

try {
    $nodeScript | node -
    if ($LASTEXITCODE -ne 0) {
        throw 'frontend API route gap scan failed'
    }
} finally {
    $env:CODEX_ROUTE_GAP_SHOW_MISSING = ''
    $env:CODEX_ROUTE_GAP_SHOW_COVERED = ''
    $env:CODEX_ROUTE_GAP_JSON = ''
    $env:CODEX_ROUTE_GAP_FAIL_ON_READ = ''
}
