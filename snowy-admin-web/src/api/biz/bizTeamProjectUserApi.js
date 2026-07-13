import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizteamprojectuser/` + url, ...arg)

/**
 * 团队项目表Api接口管理器
 *
 * @author 李治磊
 * @date  2025/01/18 16:35
 **/
export default {
	// 获取参与的团队分页
	bizTeamProjectUserPage(data) {
		return request('page', data, 'get')
	},

	bizTeamProjectUserList(data) {
		return request('list', data, 'get')
	},
	// 提交团队项目表表单 edit为true时为编辑，默认为新增
	// bizTeamProjectUserSubmitForm(data, edit = false) {
	// 	return request(edit ? 'edit' : 'add', data)
	// },

	bizTeamProjectUserAdd(data) {
		return request((data.roleType === 'MANAGE' ? 'manage/add' : 'add') + `?&teamProjectId=${data.teamProjectId}`, data)
	},

	// 删除团队项目表
	bizTeamProjectUserDelete(data) {
		return request('delete', data)
	},
	// 获取团队项目表详情
	bizTeamProjectUserDetail(data) {
		return request('detail', data, 'get')
	}
}
