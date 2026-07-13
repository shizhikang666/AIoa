import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/customerfollowup/` + url, ...arg)

/**
 * 客户跟踪记录Api接口管理器
 *
 * @author 李治磊
 * @date  2024/07/24 18:10
 **/
export default {
	// 获取客户跟踪记录分页
	customerFollowUpPage(data) {
		return request('page', data, 'get')
	},
	// 提交客户跟踪记录表单 edit为true时为编辑，默认为新增
	customerFollowUpSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除客户跟踪记录
	customerFollowUpDelete(data) {
		return request('delete', data)
	},
	// 获取客户跟踪记录详情
	customerFollowUpDetail(data) {
		return request('detail', data, 'get')
	}
}
