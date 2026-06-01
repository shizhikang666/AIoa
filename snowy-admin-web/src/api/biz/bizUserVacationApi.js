import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizuservacation/` + url, ...arg)

/**
 * 用户剩余假期记录表Api接口管理器
 *
 * @author 李治磊
 * @date  2024/10/31 08:51
 **/
export default {
	// 获取用户剩余假期记录表分页
	bizUserVacationPage(data) {
		return request('page', data, 'get')
	},
	// 提交用户剩余假期记录表表单 edit为true时为编辑，默认为新增
	bizUserVacationSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除用户剩余假期记录表
	bizUserVacationDelete(data) {
		return request('delete', data)
	},
	// 获取用户剩余假期记录表详情
	bizUserVacationDetail(data) {
		return request('detail', data, 'get')
	}
}
