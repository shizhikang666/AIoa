import CryptoJS from 'crypto-js'

export function openFilePreview(record) {
	/**
	 * var originUrl = 'http://127.0.0.1:8080/filedownload?fileId=1'; //要预览文件的访问地址
	 * var previewUrl = originUrl + '&fullfilename=test.txt'
	 * window.open('http://127.0.0.1:8012/onlinePreview?url='+encodeURIComponent(Base64.encode(previewUrl)));
	 */

	// record = {
	// 	id: '1924371398476107778',
	// 	engine: 'LOCAL',
	// 	bucket: 'defaultBucketName',
	// 	name: '智心专票合同.docx',
	// 	suffix: 'docx',
	// 	sizeKb: '229',
	// 	sizeInfo: '229.14 KB',
	// 	objName: '1924371398476107778.docx',
	// 	storagePath: '/www/wwwroot/oaJar/oajava/upload/defaultBucketName/2025/5/19/1924371398476107778.docx',
	// 	downloadPath: 'https://oa.zhixinxinli888.com/backend//dev/file/download?id=1924371398476107778',
	// 	thumbnail: null,
	// 	extJson: null
	// }
	const safeFileName = encodeURIComponent(record.fileName ? record.fileName : record.name)
	let path = record.downloadPath.replace('https://oa.zhixinxinli888.com/backend//', 'http://127.0.0.1:7971/')

	let previewUrl = `${path}&fullfilename=${record.id}-${safeFileName}`
	//http://127.0.0.1:7971/dev/file/download?id=1924371398476107778
	//previewUrl = `${record.downloadPath}&fullfilename=aa.docx`
	const encodedData = CryptoJS.enc.Base64.stringify(CryptoJS.enc.Utf8.parse(previewUrl))
	window.open(`http://47.95.5.233:7972/onlinePreview?url=` + encodeURIComponent(encodedData))
	//window.open(`http://127.0.0.1:8012/onlinePreview?url=` + encodeURIComponent(encodedData))
}

// export function openFilePreview(record) {
// 	// 定义 OnlyOffice 支持的文档类型
// 	const supportedDocumentTypes = [
// 		'.docx',
// 		'.doc',
// 		'.odt',
// 		'.rtf',
// 		'.txt',
// 		'.html',
// 		'.pdf', // 文本文档
// 		'.xlsx',
// 		'.xls',
// 		'.ods',
// 		'.csv', // 电子表格
// 		'.pptx',
// 		'.ppt',
// 		'.odp' // 演示文稿
// 	]
//
// 	// 获取文件扩展名
// 	const fileExtension = record.fileName
// 		? record.fileName.slice(record.fileName.lastIndexOf('.')).toLowerCase()
// 		: record.name.slice(record.name.lastIndexOf('.')).toLowerCase()
//
// 	// 检查文件类型是否支持
// 	if (!supportedDocumentTypes.includes(fileExtension)) {
// 		let previewUrl = `${record.downloadPath}&fullfilename=${record.id}-${
// 			record.fileName ? record.fileName : record.name
// 		}`
// 		const encodedData = CryptoJS.enc.Base64.stringify(CryptoJS.enc.Utf8.parse(previewUrl))
// 		window.open(`http://47.95.5.233:7972/onlinePreview?url=` + encodeURIComponent(encodedData))
// 		return
// 	}
// }
