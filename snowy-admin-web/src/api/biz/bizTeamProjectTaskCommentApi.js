import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizteamprojecttaskcomment/` + url, ...arg)

/**
 * 事项日志评论表Api接口管理器
 *
 * @author 李治磊
 * @date  2025/02/07 09:12
 **/
export default {
	// 获取事项日志评论表分页
	bizTeamProjectTaskCommentPage(data) {
		return request('page', data, 'get')
	},
	bizTeamProjectTaskCommentList(data) {
		return request('list', data, 'get')
	},
	// 提交事项日志评论表表单 edit为true时为编辑，默认为新增
	bizTeamProjectTaskCommentSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除事项日志评论表
	bizTeamProjectTaskCommentDelete(data) {
		return request('delete', data)
	},
	// 获取事项日志评论表详情
	bizTeamProjectTaskCommentDetail(data) {
		return request('detail', data, 'get')
	}
}
