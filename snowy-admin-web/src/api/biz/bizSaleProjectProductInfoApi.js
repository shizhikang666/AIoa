import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/saleprojectproductinfo/` + url, ...arg)

/**
 * 软件打包表Api接口管理器
 *
 * @author 李治磊
 * @date  2025/01/22 11:02
 **/
export default {
	// 获取软件打包表分页

	bizSaleProjectProductInfoList(data) {
		return request('list', data, 'get')
	},
	bizSaleProjectProductInfoPage(data) {
		return request('page', data, 'get')
	},
	// 提交软件打包表表单 edit为true时为编辑，默认为新增
	bizSaleProjectProductInfoSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除软件打包表
	bizSaleProjectProductInfoDelete(data) {
		return request('delete', data)
	},
	// 获取软件打包表详情
	bizSaleProjectProductInfoDetail(data) {
		return request('detail', data, 'get')
	}
}
