import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizpaymentrecord/` + url, ...arg)

/**
 * 收入记录表Api接口管理器
 *
 * @author 李治磊
 * @date  2024/09/12 13:41
 **/
export default {
	// 获取收入记录表分页
	bizPaymentRecordPage(data) {
		return request('page', data, 'get')
	},
	bizPaymentRecordListDetails(data) {
		return request('listdetails', data, 'get')
	},

	// 获取收入记录表详情
	bizPaymentRecordDetail(data) {
		return request('detail', data, 'get')
	},

	//收入记录列表
	bizPaymentRecordList(data) {
		return request('list', data, 'get')
	},

	bizPaymentRecordEdit(data) {
		return request('edit', data, 'post')
	},
	bizPaymentRecordEditAccount(data) {
		return request('edit/account', data, 'post')
	}
}
