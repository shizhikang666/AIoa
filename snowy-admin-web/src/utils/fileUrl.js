const FILE_DOWNLOAD_PATH = /(?:^|\/)dev\/file\/download\/?$/i

export function normalizeFileUrl(value) {
	if (typeof value !== 'string' || value.trim() === '') {
		return ''
	}

	const original = value.trim()
	try {
		const base = typeof window === 'undefined' ? 'http://localhost' : window.location.origin
		const url = new URL(original, base)
		const path = url.pathname.replace(/\/{2,}/g, '/')
		const id = url.searchParams.get('id')
		if (FILE_DOWNLOAD_PATH.test(path) && id) {
			return `/backend/dev/file/download?id=${encodeURIComponent(id)}`
		}
	} catch (error) {
		console.warn('Ignored invalid file URL', error)
	}

	return original
}

export function absoluteFileUrl(value) {
	const normalized = normalizeFileUrl(value)
	if (!normalized || typeof window === 'undefined') {
		return normalized
	}

	return new URL(normalized, window.location.origin).toString()
}
