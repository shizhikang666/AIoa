import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/saleprojectinvoice/` + url, ...arg)

/**
 * 销售项目Api接口管理器
 *
 * @author 李治磊
 * @date  2024/07/26 19:45
 **/
export default {

	//获取项目发货单
	bizSaleProjectInvoiceList(data) {

		return request('list', data, 'get')

	}




}
