import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/warehouses/` + url, ...arg)

/**
 * 系统仓库表Api接口管理器
 *
 * @author 李治磊
 * @date  2024/07/19 09:37
 **/
export default {
	// 获取系统仓库表分页
	warehousesPage(data) {
		return request('page', data, 'get')
	},
	warehousesList(data) {
		return request('list', data, 'get')
	},
	// 提交系统仓库表表单 edit为true时为编辑，默认为新增
	warehousesSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除系统仓库表
	warehousesDelete(data) {
		return request('delete', data)
	},
	// 获取系统仓库表详情
	warehousesDetail(data) {
		return request('detail', data, 'get')
	}
}
