param(
    [switch]$ShowDeferred
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $ProjectRoot

$nodeScript = @'
const fs = require('fs');
const path = require('path');

const root = process.cwd();
const srcDir = path.join(root, 'snowy-admin-web', 'src');
const showDeferred = process.argv.includes('--show-deferred');

function walk(dir, out = []) {
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name);
    if (ent.isDirectory()) {
      walk(p, out);
    } else if (/\.(vue|js)$/.test(ent.name)) {
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

function resolveImport(file, spec) {
  let apiPath = spec.startsWith('@/')
    ? path.join(srcDir, spec.slice(2))
    : path.resolve(path.dirname(file), spec);
  if (!path.extname(apiPath)) {
    apiPath += '.js';
  }
  return apiPath;
}

function exportedMethods(apiText) {
  const methods = new Set();
  const pattern = /(?:^|[,{;\n])\s*(?:async\s+)?([A-Za-z_$][\w$]*)\s*\([^)]*\)\s*\{/g;
  for (const match of apiText.matchAll(pattern)) {
    const name = match[1];
    if (!['if', 'for', 'while', 'switch', 'function'].includes(name)) {
      methods.add(name);
    }
  }
  return methods;
}

const writeLike = /(SubmitForm|Delete|Add|Edit|ApplyApproval|Complete|Cancel|Reject|Approve|Visibility|Amount|Deal|Repeal|Import|Export|Run|Stop|Send|Grant|Reset|Disable|Enable|MarkRead|Update|Upload|Password|Avatar|Signature|Workbench|Config)$/i;
const files = []
  .concat(walk(path.join(srcDir, 'views')))
  .concat(walk(path.join(srcDir, 'components')));
const missingRead = new Set();
const deferredWrite = new Set();

for (const file of files) {
  const text = stripComments(fs.readFileSync(file, 'utf8'));
  const imports = [];
  for (const match of text.matchAll(/import\s+([A-Za-z_$][\w$]*)\s+from\s+['"]([^'"]*api[^'"]*)['"]/g)) {
    const local = match[1];
    const apiPath = resolveImport(file, match[2]);
    if (fs.existsSync(apiPath)) {
      imports.push({ local, apiPath });
    }
  }

  for (const { local, apiPath } of imports) {
    const apiText = stripComments(fs.readFileSync(apiPath, 'utf8'));
    const exported = exportedMethods(apiText);
    const callPattern = new RegExp(local.replace(/[$]/g, '\\$&') + '\\s*\\.\\s*([A-Za-z_$][\\w$]*)\\s*\\(', 'g');
    for (const call of text.matchAll(callPattern)) {
      const method = call[1];
      if (exported.has(method)) {
        continue;
      }

      const item = `${rel(file)} calls ${local}.${method} but ${rel(apiPath)} does not export it`;
      if (writeLike.test(method)) {
        deferredWrite.add(item);
      } else {
        missingRead.add(item);
      }
    }
  }
}

if (missingRead.size > 0) {
  console.error([...missingRead].sort().join('\n'));
  process.exit(1);
}

console.log('frontend API method smoke passed');
if (showDeferred && deferredWrite.size > 0) {
  console.log('');
  console.log('deferred write-like missing methods:');
  console.log([...deferredWrite].sort().join('\n'));
}
'@

$args = @('-e', $nodeScript)
if ($ShowDeferred) {
    $args += '--'
    $args += '--show-deferred'
}

& node $args
if ($LASTEXITCODE -ne 0) {
    throw 'frontend API method smoke failed'
}
