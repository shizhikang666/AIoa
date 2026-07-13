import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizdatareport/` + url, ...arg)

/**
 * 大数据统计接口用于统计销售额度！
 *
 * @author 李治磊
 * @date  2024/08/09 18:01
 **/
export default {
	bizSaleProjectDataReport(data) {
		return request('saleproject', data, 'post')
	},
	bizSaleProjectDataList(data) {
		return request('saleproject/list', data, 'post')
	},
	bizSaleProjectDataListDetails(data) {
		return request('saleProjectList/details', data, 'post')
	},

	bizSaleProjectDataReportUnpaidPayment(data) {
		return request('saleproject/UnpaidPayment', data, 'post')
	},
	bizSaleProjectDataReportList(data) {
		return request('saleproject/report', data, 'post')
	},
	bizSettlementAccountIncome(data) {
		return request('settlement/income', data, 'post')
	},
	bizSettlementAccountExpenses(data) {
		return request('settlement/expenses', data, 'post')
	},
	summaryStatistics(data) {
		return request('summary/statistics', data, 'post')
	},
	saleProfit(data) {
		return request('saleProfit', data, 'post')
	}
}
