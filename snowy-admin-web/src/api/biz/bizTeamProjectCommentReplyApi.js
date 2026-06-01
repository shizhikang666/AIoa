import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizteamprojectcommentreply/` + url, ...arg)

/**
 * 时间轴回复表Api接口管理器
 *
 * @author 李治磊
 * @date  2025/01/23 11:36
 **/
export default {
	// 获取时间轴回复表分页
	bizTeamProjectCommentReplyPage(data) {
		return request('page', data, 'get')
	},
	// 提交时间轴回复表表单 edit为true时为编辑，默认为新增
	bizTeamProjectCommentReplySubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除时间轴回复表
	bizTeamProjectCommentReplyDelete(data) {
		return request('delete', data)
	},
	// 获取时间轴回复表详情
	bizTeamProjectCommentReplyDetail(data) {
		return request('detail', data, 'get')
	}
}
