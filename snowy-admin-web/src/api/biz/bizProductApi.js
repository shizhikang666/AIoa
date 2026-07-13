import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizproduct/` + url, ...arg)

/**
 * 产品管理Api接口管理器
 *
 * @author 李治磊
 * @date  2024/07/20 20:18
 **/
export default {
	// 获取产品管理分页
	bizProductPage(data) {
		return request('page', data, 'get')
	},
	bizProductList(data) {
		return request('list', data, 'get')
	},
	bizProductChildren(data) {
		return request('children', data)
	},
	// 提交产品管理表单 edit为true时为编辑，默认为新增
	bizProductSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除产品管理
	bizProductDelete(data) {
		return request('delete', data)
	},
	// 获取产品管理详情
	bizProductDetail(data) {
		return request('detail', data, 'get')
	},
	bizProductReconciliationEdit(data) {
		return request('reconciliation/edit', data)
	},
	bizProductEditStatus(data) {
		return request('edit/status', data)
	}
}
