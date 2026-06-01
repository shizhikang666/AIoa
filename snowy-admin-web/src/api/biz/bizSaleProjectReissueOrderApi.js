import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/saleprojectreissueorder/` + url, ...arg)

/**
 * 项目补发单Api接口管理器
 *
 * @author 李治磊
 * @date  2024/09/19 16:20
 **/
export default {
	// 获取项目补发单列表
	async bizSaleProjectReissueOrderListDetail(data) {
		const result = await request('list/query', data, 'get')

		result.forEach((item) => {
			if (item.productItemList) {
				item.productItemList.forEach((v) => {
					if (v.children) {
						v.children.forEach((childrenItem) => {
							const product = JSON.parse(childrenItem.extJson).product
							childrenItem.productName = product.productName
							childrenItem.productSysCategory = product.category
							childrenItem.specs = product.specs
							childrenItem.productCategory = product.productCategory
						})
					}
				})
			}
		})

		return result
	}
}
