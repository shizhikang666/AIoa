import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/aftersales/` + url, ...arg)

export default {
	afterSalesPage(data) {
		return request('page', data, 'get')
	},
	afterSalesDetail(data) {
		return request('detail', data, 'get')
	},
	afterSalesSubmit(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	afterSalesDelete(data) {
		return request('delete', data)
	},
	categoryList(data = {}) {
		return request('category/list', data, 'get')
	},
	categorySubmit(data, edit = false) {
		return request(edit ? 'category/edit' : 'category/add', data)
	},
	categoryDelete(data) {
		return request('category/delete', data)
	}
}
