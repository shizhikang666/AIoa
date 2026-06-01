import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizleaveapplication/` + url, ...arg)

/**
 * 请假记录表Api接口管理器
 *
 * @author 李治磊
 * @date  2025/01/16 09:16
 **/
export default {
	// 获取请假记录表分页
	bizLeaveApplicationPage(data) {
		return request('page', data, 'get')
	},
	bizLeaveApplicationMyPage(data) {
		return request('my/page', data, 'get')
	},
	// 提交请假记录表表单 edit为true时为编辑，默认为新增
	bizLeaveApplicationSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除请假记录表
	bizLeaveApplicationDelete(data) {
		return request('delete', data)
	},
	// 获取请假记录表详情
	bizLeaveApplicationDetail(data) {
		return request('detail', data, 'get')
	}
}
