import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/tenants/tenant/` + url, ...arg)

/**
 * 租户表Api接口管理器
 *
 * @author 李治磊
 * @date  2024/07/07 10:01
 **/
export default {
	// 获取租户表分页
	tenantsPage(data) {
		return request('page', data, 'get')
	},
	// 提交租户表表单 edit为true时为编辑，默认为新增
	tenantsSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除租户表
	tenantsDelete(data) {
		return request('delete', data)
	},
	// 获取租户表详情
	tenantsDetail(data) {
		return request('detail', data, 'get')
	}
}
