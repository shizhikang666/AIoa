import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizhistoryexcel/` + url, ...arg)

/**
 * 历史EXCLE数据表Api接口管理器
 *
 * @author 李治磊
 * @date  2026/01/08 17:54
 **/
export default {
	// 获取历史EXCLE数据表分页
	bizHistoryExcelPage(data) {
		return request('page', data, 'get')
	},
	// 提交历史EXCLE数据表表单 edit为true时为编辑，默认为新增
	bizHistoryExcelSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除历史EXCLE数据表
	bizHistoryExcelDelete(data) {
		return request('delete', data)
	},
	// 获取历史EXCLE数据表详情
	bizHistoryExcelDetail(data) {
		return request('detail', data, 'get')
	}
}
