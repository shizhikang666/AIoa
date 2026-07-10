export function safeJsonParse(value, fallback = null) {
	if (value !== null && typeof value === 'object') {
		return value
	}
	if (typeof value !== 'string' || value.trim() === '') {
		return fallback
	}

	try {
		return JSON.parse(value)
	} catch (error) {
		console.warn('Ignored invalid JSON data', error)
		return fallback
	}
}
