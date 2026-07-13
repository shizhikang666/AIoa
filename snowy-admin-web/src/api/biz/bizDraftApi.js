import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizdraft/` + url, ...arg)

/**
 * 草稿表Api接口管理器
 *
 * @author 李治磊
 * @date  2024/10/08 16:35
 **/
export default {
	// 提交草稿表表单 edit为true时为编辑，默认为新增
	bizDraftSubmitSaleProjectForm(data) {
		return request('saleproject/add', data)
	},
	// 获取草稿表详情
	bizDraftDetail(data) {
		return request('detail', data, 'get')
	}
}
