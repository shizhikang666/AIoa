import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizteamprojecttask/` + url, ...arg)

/**
 * 事项任务表Api接口管理器
 *
 * @author 李治磊
 * @date  2025/02/07 09:10
 **/
export default {
	// 获取事项任务表分页
	bizTeamProjectTaskPage(data) {
		return request('page', data, 'get')
	},
	// 获取事项任务列表
	bizTeamProjectTaskList(data) {
		return request('list', data, 'get')
	},
	bizTeamProjectTaskEdit(data) {
		return request('edit', data)
	},
	bizTeamProjectTaskUserEdit(data) {
		return request('user/edit', data)
	},
	// 提交事项任务表表单 edit为true时为编辑，默认为新增
	bizTeamProjectTaskSubmitForm(data, edit = false) {
		return request((edit ? 'edit' : 'add') + `?&teamProjectId=${data.teamProjectId}`, data)
	},
	// 删除事项任务表
	bizTeamProjectTaskDelete(data) {
		return request('delete', data)
	},
	// 获取事项任务表详情
	bizTeamProjectTaskDetail(data) {
		return request('detail', data, 'get')
	}
}
