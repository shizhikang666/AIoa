import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/inventory/` + url, ...arg)

/**
 * 仓库库存Api接口管理器
 *
 * @author 李治磊
 * @date  2024/07/19 16:40
 **/
export default {
	// 获取仓库库存分页
	inventoryPage(data) {
		return request('page', data, 'get')
	},
	inventoryList(data) {
		return request('list', data, 'get')
	},
	// 提交仓库库存表单 edit为true时为编辑，默认为新增
	inventoryAdd(data) {
		return request('add', data)
	},
	// 删除仓库库存
	inventoryDelete(data) {
		return request('delete', data)
	},
	// 获取仓库库存详情
	inventoryDetail(data) {
		return request('detail', data, 'get')
	}
}
