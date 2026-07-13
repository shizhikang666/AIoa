import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/warehouses/delivery/` + url, ...arg)

/**
 * 仓库出入库记录Api接口管理器
 *
 * @author 李治磊
 * @date  2024/09/03 19:32
 **/
export default {
	// 获取仓库出入库记录分页
	deliveryRecordPage(data) {
		return request('page', data, 'get')
	},

	exportOtherCompanyRecordsList(data) {
		return request('exportOtherCompanyRecordsList', data, 'get')
	},
	deliveryRecordAdd(data) {
		return request('add', data)
	},
	// 获取仓库出入库记录详情
	deliveryRecordDetail(data) {
		return request('detail', data, 'get')
	}
}
