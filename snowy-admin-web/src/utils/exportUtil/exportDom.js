// 引入图片转base64的js

import { createReport } from 'docx-templates/lib/browser'

const saveDataToFile = (data, fileName, mimeType) => {
	const blob = new Blob([data], { type: mimeType })
	const url = window.URL.createObjectURL(blob)
	downloadURL(url, fileName, mimeType)
	setTimeout(() => {
		window.URL.revokeObjectURL(url)
	}, 1000)
}

const downloadURL = (data, fileName) => {
	const a = document.createElement('a')
	a.href = data
	a.download = fileName
	document.body.appendChild(a)
	a.style = 'display: none'
	a.click()
	a.remove()
}

export async function exportWordDocx(path, wordData, config) {
	const Config = Object.assign(
		{
			cmdDelimiter: ['{', '}'],
			filename: 'report.docx'
		},
		config
	)

	const template = await fetch(path).then((res) => res.arrayBuffer())
	console.log(wordData)
	const report = await createReport({
		template,
		data: wordData,
		cmdDelimiter: Config.cmdDelimiter
	})
	saveDataToFile(report, Config.filename, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
}
