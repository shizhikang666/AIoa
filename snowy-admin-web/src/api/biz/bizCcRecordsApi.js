import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/ccrecords/` + url, ...arg)

/**
 * 抄送记录表Api接口管理器
 *
 * @author 李治磊
 * @date  2024/08/09 18:01
 **/
export default {
	// 获取抄送记录表分页
	bizCcRecordsPage(data) {
		return request('page', data, 'get')
	},
	// 提交抄送记录表表单 edit为true时为编辑，默认为新增
	bizCcRecordsSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除抄送记录表
	bizCcRecordsDelete(data) {
		return request('delete', data)
	},
	// 获取抄送记录表详情
	bizCcRecordsDetail(data) {
		return request('detail', data, 'get')
	}
}
