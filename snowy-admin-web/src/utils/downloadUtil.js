/**
 *  Copyright [2022] [https://www.xiaonuo.vip]
 *	Snowy采用APACHE LICENSE 2.0开源协议，您在使用过程中，需要注意以下几点：
 *	1.请不要删除和修改根目录下的LICENSE文件。
 *	2.请不要删除和修改Snowy源码头部的版权声明。
 *	3.本项目代码可免费商业使用，商业使用请保留源码和相关描述文件的项目出处，作者声明等。
 *	4.分发源码时候，请注明软件出处 https://www.xiaonuo.vip
 *	5.不可二次分发开源参与同类竞品，如有想法可联系团队xiaonuobase@qq.com商议合作。
 *	6.若您的项目无法满足以上几点，需要更多功能代码，获取Snowy商业授权许可，请在官网购买授权，地址为 https://www.xiaonuo.vip
 */
import { message } from 'ant-design-vue'

const trimQuotes = (value) => {
	value = String(value || '').trim()
	if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
		return value.slice(1, -1)
	}
	return value
}

const decodeHeaderFilename = (value) => {
	value = trimQuotes(value)
	try {
		return decodeURIComponent(value)
	} catch (e) {
		return value
	}
}

const getContentDisposition = (headers) => {
	return headers?.['content-disposition'] || headers?.get?.('content-disposition') || ''
}

const getDownloadFilename = (contentDisposition) => {
	if (!contentDisposition) {
		return 'download'
	}

	const utf8Match = contentDisposition.match(/filename\*\s*=\s*(?:[^']*'[^']*')?([^;]+)/i)
	if (utf8Match?.[1]) {
		return decodeHeaderFilename(utf8Match[1])
	}

	const filenameMatch = contentDisposition.match(/filename\s*=\s*("[^"]*"|[^;]+)/i)
	if (filenameMatch?.[1]) {
		return decodeHeaderFilename(filenameMatch[1])
	}

	return 'download'
}

export default {
	// 对下载的流进行处理，直接从浏览器下载下来
	resultDownload(res) {
		if (String(res.data?.type || '').includes('application/json')) {
			// 错误以及无权限
			const reader = new FileReader(res.data)

			reader.readAsText(res.data)

			reader.onload = () => {
				const result = JSON.parse(reader.result)
				message.error(result.msg)
			}
		} else {
			const blob = new Blob([res.data], { type: res.data?.type || 'application/octet-stream;charset=UTF-8' })
			const contentDisposition = getContentDisposition(res.headers)
			const $link = document.createElement('a')
			$link.href = URL.createObjectURL(blob)
			$link.download = getDownloadFilename(contentDisposition)
			document.body.appendChild($link)
			$link.click()
			document.body.removeChild($link) // 下载完成移除元素
			window.URL.revokeObjectURL($link.href) // 释放掉blob对象
		}
	}
}
