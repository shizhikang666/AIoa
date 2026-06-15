#!/usr/bin/env node

const fs = require('fs')

const path = process.argv[2] || ''
const input = fs.readFileSync(0, 'utf8')

function fail(message) {
	process.stderr.write(`${message}\n`)
	process.exit(1)
}

function readPath(value, expression) {
	if (!expression || expression === '.') {
		return value
	}

	let current = value
	const parts = expression.split('.').filter(Boolean)
	for (const part of parts) {
		if (current === null || current === undefined) {
			return undefined
		}

		if (Array.isArray(current) && /^\d+$/.test(part)) {
			current = current[Number(part)]
			continue
		}

		current = current[part]
	}

	return current
}

let parsed
try {
	parsed = JSON.parse(input)
} catch (error) {
	fail(`invalid JSON: ${error.message}`)
}

const result = readPath(parsed, path)
if (result === undefined) {
	process.exit(2)
}

if (typeof result === 'string') {
	process.stdout.write(result)
} else {
	process.stdout.write(JSON.stringify(result))
}
