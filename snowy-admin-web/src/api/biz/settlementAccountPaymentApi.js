import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/settlementaccountpayment/` + url, ...arg)

/**
 * 结算账户表Api接口管理器
 *
 * @author 系统
 * @date  2024/07/10 09:12
 **/
export default {
	// 获取结算账户表分页
	settlementAccountPaymentPage(data) {
		return request('page', data, 'get')
	},
	settlementAccountPaymentList(data) {
		return request('list', data, 'get')
	}
}
