import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizcollectionreceipt/` + url, ...arg)

/**
 * 代收款单Api接口管理器
 *
 * @author 李治磊
 * @date  2024/10/29 13:50
 **/
export default {

	batchExpenditure(data){
		return request('batchExpenditure/edit',data)
	},
	// 获取代收款单分页
	bizCollectionReceiptPage(data) {
		return request('page', data, 'get')
	},
	bizCollectionReceiptList(data) {
		return request('list', data, 'get')
	},
	// 提交代收款单表单 edit为true时为编辑，默认为新增
	bizCollectionReceiptSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	mark(data) {
		return request('mark/success/edit', data)
	},
	// 删除代收款单
	bizCollectionReceiptDelete(data) {
		return request('delete', data)
	},
	// 获取代收款单详情
	bizCollectionReceiptDetail(data) {
		return request('detail', data, 'get')
	}
}
