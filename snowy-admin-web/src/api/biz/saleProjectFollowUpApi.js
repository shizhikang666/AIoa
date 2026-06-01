import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/saleprojectfollowup/` + url, ...arg)

/**
 * 销售项目跟踪记录Api接口管理器
 *
 * @author 李治磊
 * @date  2024/07/29 09:01
 **/
export default {
	// 获取销售项目跟踪记录分页
	saleProjectFollowUpPage(data) {
		return request('page', data, 'get')
	},
	// 提交销售项目跟踪记录表单 edit为true时为编辑，默认为新增
	saleProjectFollowUpSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除销售项目跟踪记录
	saleProjectFollowUpDelete(data) {
		return request('delete', data)
	},
	// 获取销售项目跟踪记录详情
	saleProjectFollowUpDetail(data) {
		return request('detail', data, 'get')
	}
}
