import { baseRequest } from '@/utils/request'
import { safeJsonParse } from '@/utils/json'

const request = (url, ...arg) => baseRequest(`/biz/saleproject/` + url, ...arg)

/**
 * 销售项目Api接口管理器
 *
 * @author 李治磊
 * @date  2024/07/26 19:45
 **/
export default {
	// 获取销售项目分页
	bizSaleProjectPage(data) {
		return request('page', data, 'get')
	},
	bizSaleProjectCasePage(data) {
		return request('case/page', data, 'get')
	},
	bizSaleProjectHistoryAdd(data) {
		return request('history/add', data)
	},
	bizSpecialProjectAdd(data) {
		return request('special/add', data)
	},

	bizSaleProjectOperationPage(data) {
		return request('operation/page', data, 'get')
	},

	// 获取销售项目列表
	bizSaleProjectListDetail(data) {
		return request('list/detail', data, 'get')
	},
	bizSaleProjecPublicPage(data) {
		return request('public/page', data, 'get')
	},
	// 提交销售项目表单 edit为true时为编辑，默认为新增
	bizSaleProjectSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	bizSaleProjectEditDealProject(data) {
		return request('deal/edit', data)
	},
	bizSaleProjectVisibilityEdit(data) {
		return request('visibility/edit', data)
	},
	// 删除销售项目
	bizSaleProjectDelete(data) {
		return request('delete', data)
	},
	repealBizSaleProject(data) {
		return request('repeal', data)
	},
	cancelBizSaleProject(data) {
		return request('cancel', data)
	},
	costBizSaleProject(data) {
		return request('cost', data)
	},
	costBizSaleProjectDetails(data) {
		return request('cost/details', data)
	},
	editBizSaleProjectAmount(data) {
		return request('amount/edit', data)
	},

	// 获取销售项目详情
	async bizSaleProjectDetail(data) {
		const result = await request('detail', data, 'get')
		if (result.productItems) {
			result.productItems.forEach((v) => {
				if (v.children) {
					v.children.forEach((childrenItem) => {
						const product = safeJsonParse(childrenItem.extJson, {}).product || {}
						childrenItem.productName = product.productName
						childrenItem.productSysCategory = product.category
						childrenItem.specs = product.specs
						childrenItem.productCategory = product.productCategory
					})
				}
			})
		}

		return result
	},
	//获取销售产品字表
	async bizSaleProjectProductItemList(data) {
		const result = await request('product', data, 'get')
		result.forEach((v) => {
			if (v.children) {
				v.children.forEach((childrenItem) => {
					const product = safeJsonParse(childrenItem.extJson, {}).product || {}
					childrenItem.productName = product.productName
					childrenItem.productSysCategory = product.category
					childrenItem.specs = product.specs
					childrenItem.productCategory = product.productCategory
				})
			}
		})

		return result
	},
	// 获取项目的发货安排。旧项目没有安排时返回空数组，继续使用原发货流程。
	bizSaleProjectDeliveryPlanList(data) {
		return request('delivery/plan/list', data, 'get')
	}
}
