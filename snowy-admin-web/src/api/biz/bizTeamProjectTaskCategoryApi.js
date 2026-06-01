import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizteamprojecttaskcategory/` + url, ...arg)

/**
 * 事项任务分类Api接口管理器
 *
 * @author 李治磊
 * @date  2025/02/25 13:47
 **/
export default {
	// 获取事项任务分类分页
	bizTeamProjectTaskCategoryPage(data) {
		return request('page', data, 'get')
	},
	bizTeamProjectTaskCategoryList(data) {
		return request('list', data, 'get')
	},
	// 提交事项任务分类表单 edit为true时为编辑，默认为新增
	bizTeamProjectTaskCategorySubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	//排序事项分类
	bizTeamProjectTaskCategorySort(data) {
		return request('sort/edit', data)
	},
	// 删除事项任务分类
	bizTeamProjectTaskCategoryDelete(data) {
		return request('delete', data)
	},
	// 获取事项任务分类详情
	bizTeamProjectTaskCategoryDetail(data) {
		return request('detail', data, 'get')
	}
}
