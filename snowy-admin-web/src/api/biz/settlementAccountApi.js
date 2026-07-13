import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/settlementaccount/` + url, ...arg)

/**
 * 结算账户表Api接口管理器
 *
 * @author 系统
 * @date  2024/07/10 09:12
 **/
export default {
	// 获取结算账户表分页
	settlementAccountPage(data) {
		return request('page', data, 'get')
	},
	//获取所有启用的结算账户
	settlementAccountList(data) {
		return request('list', data, 'get')
	},
	// 提交结算账户表表单 edit为true时为编辑，默认为新增
	settlementAccountSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除结算账户表
	settlementAccountDelete(data) {
		return request('delete', data)
	},
	// 获取结算账户表详情
	settlementAccountDetail(data) {
		return request('detail', data, 'get')
	},

	settlementAccountEditStatus(data) {
		return request('edit/status', data, 'post')
	},

	settlementAccountName(data) {
		return request('queryName', data, 'get')
	},
	settlementAccountExpenses(data) {
		return request('expenses/add', data, 'post')
	},
	settlementAccountPayment(data) {
		return request('payment/add', data, 'post')
	},
	settlementAccountTransfer(data) {
		return request('transfer/add', data, 'post')
	}
}
