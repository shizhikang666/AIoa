import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/customer/` + url, ...arg)

/**
 * 客户Api接口管理器
 *
 * @author 李治磊
 * @date  2024/07/16 15:50
 **/
export default {
	// 获取客户分页
	customerPage(data) {
		return request('page', data, 'get')
	},
	// 提交客户表单 edit为true时为编辑，默认为新增
	customerSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除客户
	customerDelete(data) {
		return request('delete', data)
	},
	// 获取客户详情
	customerDetail(data) {
		return request('detail', data, 'get')
	},
	customerDetailList(data) {
		return request('detail/list', data, 'post')
	},
	customerHeadEdit(data) {
		return request('head/edit', data, 'post')
	}
}
