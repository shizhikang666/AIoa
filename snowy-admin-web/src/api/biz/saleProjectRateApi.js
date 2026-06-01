import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/projectrate/` + url, ...arg)

/**
 * 销售项目客户点评Api接口管理器
 *
 * @author 李治磊
 * @date  2024/12/21 09:31
 **/
export default {
	async list(data) {
		const res = await request(`list`, data, 'get')
		res.forEach((item) => {
			if (item.extJson) {
				const { imgList } = JSON.parse(item.extJson)
				item.imgList = imgList.map((item) => {
					return item.replace('http://47.95.5.233:7971/', 'https://oa.zhixinxinli888.com/backend/')
				})
			} else {
				item.imgList = []
			}
		})
		return res
	},
	// 获取销售项目客户点评分页
	saleProjectRatePage(data) {
		return request('page', data, 'get')
	},
	// 提交销售项目客户点评表单 edit为true时为编辑，默认为新增
	saleProjectRateSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除销售项目客户点评
	saleProjectRateDelete(data) {
		return request('delete', data)
	},
	// 获取销售项目客户点评详情
	saleProjectRateDetail(data) {
		return request('detail', data, 'get')
	}
}
