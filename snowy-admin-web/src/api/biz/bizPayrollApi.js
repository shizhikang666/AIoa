import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizpayroll/` + url, ...arg)

/**
 * 工资单Api接口管理器
 *
 * @author 李治磊
 * @date  2024/11/01 16:58
 **/
export default {
	downloadImportTemplate(data) {
		return request('downloadImportTemplate', data, 'get', {
			responseType: 'blob'
		})
	},
	importExcel(data) {
		return request('import', data)
	},
	// 获取工资单分页
	bizPayrollPage(data) {
		return request('page', data, 'get')
	},
	bizPayrollMyPage(data) {
		return request('mypage', data, 'get')
	},

	bizPayrollListGenerate(data) {
		return request('generate/add', data)
	},

	bizPayrollEdit(data) {
		return request('edit', data)
	},
	bizPayrollBathEdit(data) {
		return request('bath/edit', data)
	},

	// 提交工资单表单 edit为true时为编辑，默认为新增
	bizPayrollSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除工资单
	bizPayrollDelete(data) {
		return request('delete', data)
	},
	// 删除工资单
	bizPayrollExport(data) {
		return request('export', data, 'get', {
			responseType: 'blob'
		})
	},
	// 获取工资单详情
	bizPayrollDetail(data) {
		return request('detail', data, 'get')
	}
}
