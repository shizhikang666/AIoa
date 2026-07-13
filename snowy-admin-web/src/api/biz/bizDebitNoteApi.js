import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizdebitnote/` + url, ...arg)

/**
 * 借款/代付单Api接口管理器
 *
 * @author 李治磊
 * @date  2024/10/29 16:29
 **/
export default {
	// 获取借款/代付单分页
	bizDebitNotePage(data) {
		return request('page', data, 'get')
	},
	bizDebitNoteList(data) {
		return request('list', data, 'get')
	},
	batchRepayment(data) {
		return request('batchRepayment/edit', data)
	},
	bizDebitNoteSubmitHistory(data) {
		return request('history/add', data)
	},
	// 提交借款/代付单表单 edit为true时为编辑，默认为新增
	bizDebitNoteSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	mark(data) {
		return request('mark/success/edit', data)
	},
	// 删除借款/代付单
	bizDebitNoteDelete(data) {
		return request('delete', data)
	},
	// 获取借款/代付单详情
	bizDebitNoteDetail(data) {
		return request('detail', data, 'get')
	}
}
