import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizteamproject/` + url, ...arg)

/**
 * 待办事项管理Api接口管理器
 *
 * @author 李治磊
 * @date  2025/01/18 15:51
 **/
export default {
	// 获取待办事项管理分页
	bizTeamProjectPage(data) {
		return request('page', data, 'get')
	},

	// 提交待办事项管理表单 edit为true时为编辑，默认为新增
	bizTeamProjectSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除待办事项管理
	bizTeamProjectDelete(data) {
		return request('delete', data)
	},
	// 获取待办事项管理详情
	bizTeamProjectDetail(data) {
		return request('detail', data, 'get')
	}
}
