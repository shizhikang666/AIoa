import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/supplier/` + url, ...arg)

/**
 * 供应商Api接口管理器
 *
 * @author 李治磊
 * @date  2024/07/15 17:50
 **/
export default {
	// 获取供应商分页
	supplierPage(data) {
		return request('page', data, 'get')
	},
	// 获取供应商列表
	supplierList(data) {
		return request('list', data, 'get')
	},
	//更具供应商名字查询系统现有供应商信息
	supplierListQueryByName(data) {
		return request('list/query/name', data, 'get')
	},

	// 提交供应商表单 edit为true时为编辑，默认为新增
	supplierSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	// 删除供应商
	supplierDelete(data) {
		return request('delete', data)
	},
	// 获取供应商详情
	supplierDetail(data) {
		return request('detail', data, 'get')
	}
}
