import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/saleprojectinvoicing/` + url, ...arg)

/**
 * 开票信息表Api接口管理器
 *
 * @author 李治磊
 * @date  2024/09/25 09:55
 **/
export default {
	// 获取开票信息表分页
	bizSaleProjectInvoicingPage(data) {
		return request('page', data, 'get')
	},
	// 提交开票信息表表单 edit为true时为编辑，默认为新增
	bizSaleProjectInvoicingSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除开票信息表
	bizSaleProjectInvoicingDelete(data) {
		return request('delete', data)
	},

	bizSaleProjectInvoicingComplete(data) {
		return request('complete', data, 'post')
	},

	//根据客户id获取最近开票信息
	bizSaleProjectInvoicingQueryCustomer(data) {
		return request('customer', data, 'get')
	},




	// 获取开票信息表详情
	bizSaleProjectInvoicingDetail(data) {
		return request('detail', data, 'get')
	}
}
