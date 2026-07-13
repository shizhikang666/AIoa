import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizpurchaseorder/` + url, ...arg)

/**
 * 采购单Api接口管理器
 *
 * @author 李治磊
 * @date  2024/09/11 14:35
 **/
export default {
	bizPurchaseInWarehouse(data) {
		return request('warehouse/add', data)
	},

	bizPurchaseInOneWarehouse(data) {
		return request('warehouse/one/add', data)
	},
	// 获取采购单分页
	bizPurchaseOrderPage(data) {
		return request('page', data, 'get')
	},
	bizPurchaseOrderDetailList(data) {
		return request('detail/list', data, 'get')
	},
	bizPurchaseOrderList(data) {
		return request('list', data, 'get')
	},
	// 提交采购单表单 edit为true时为编辑，默认为新增
	bizPurchaseOrderSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	bizPurchaseOrderAuditEdit(data) {
		return request('audit/edit', data)
	},

	// 删除采购单
	bizPurchaseOrderDelete(data) {
		return request('delete', data)
	},
	// 获取采购单详情
	bizPurchaseOrderDetail(data) {
		return request('detail', data, 'get')
	},
	bizPurchaseOrderCancel(data) {
		return request('cancel', data)
	}
}
