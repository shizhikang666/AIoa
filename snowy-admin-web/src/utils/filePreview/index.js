import CryptoJS from 'crypto-js'
import { absoluteFileUrl } from '@/utils/fileUrl'

export function openFilePreview(record) {
	const sourceUrl = absoluteFileUrl(record?.downloadPath)
	if (!sourceUrl) {
		return
	}

	const previewUrl = new URL(sourceUrl)
	const fileName = record?.fileName || record?.name || 'file'
	previewUrl.searchParams.set('fullfilename', `${record?.id || 'file'}-${fileName}`)

	const previewServer = String(import.meta.env.VITE_FILE_PREVIEW_URL || '').trim().replace(/\/$/, '')
	if (!previewServer) {
		window.open(previewUrl.toString(), '_blank', 'noopener,noreferrer')
		return
	}

	const encodedData = CryptoJS.enc.Base64.stringify(CryptoJS.enc.Utf8.parse(previewUrl.toString()))
	window.open(`${previewServer}/onlinePreview?url=${encodeURIComponent(encodedData)}`, '_blank', 'noopener,noreferrer')
}
