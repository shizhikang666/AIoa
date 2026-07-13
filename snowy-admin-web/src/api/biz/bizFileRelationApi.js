import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizfilerelation/` + url, ...arg)

/**
 * 业务文件关系Api接口管理器
 *
 * @author 李治磊
 * @date  2024/09/02 16:59
 **/
export default {
	// 获取业务文件关系分页
	bizFileRelationPage(data) {
		return request('page', data, 'get')
	},
	bizFileRelationList(data) {
		return request('list', data, 'get')
	},
	// 提交业务文件关系表单 edit为true时为编辑，默认为新增
	bizFileRelationSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除业务文件关系
	bizFileRelationDelete(data) {
		return request('delete', data)
	},
	// 获取业务文件关系详情
	bizFileRelationDetail(data) {
		return request('detail', data, 'get')
	}
}
