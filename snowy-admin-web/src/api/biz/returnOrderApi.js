import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/returnorder/` + url, ...arg)

/**
 * 退货单表Api接口管理器
 *
 * @author 李治磊
 * @date  2024/12/16 11:16
 **/
export default {
	// 获取退货单表分页
	returnOrderPage(data) {
		return request('page', data, 'get')
	},
	// 查询指定退货单表列表
	returnOrderQuery(data) {
		return request('query', data, 'get')
	}
}
