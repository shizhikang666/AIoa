import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizteamprojectcomment/` + url, ...arg)

/**
 * 事项任务表Api接口管理器
 *
 * @author 李治磊
 * @date  2025/01/21 16:43
 **/
export default {
	// 获取事项任务表分页
	bizTeamProjectCommentPage(data) {
		return request('page', data, 'get')
	},
	// 提交事项任务表表单 edit为true时为编辑，默认为新增
	bizTeamProjectCommentList(data) {
		return request('list', data, 'get')
	},
	bizTeamProjectCommentAdd(data) {
		return request(`add?&teamProjectId=${data.teamProjectId}`, data)
	},
	// 删除事项任务表
	bizTeamProjectCommentDelete(data) {
		return request('delete', data)
	},
	// 获取事项任务表详情
	bizTeamProjectCommentDetail(data) {
		return request('detail', data, 'get')
	}
}
