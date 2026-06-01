import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/bizexpenditurerecord/` + url, ...arg)

/**
 * 支出记录表Api接口管理器
 *
 * @author 李治磊
 * @date  2024/09/26 15:40
 **/
export default {
	// 获取支出记录表分页
	bizExpenditureRecordPage(data) {
		return request('page', data, 'get')
	},
	bizExpenditureRecordListDetails(data) {
		if (Array.isArray(data.settlementCategory)) {
			data.settlementCategory = data.settlementCategory.join(',')
		}

		return request('listDetails', data, 'get')
	},
	// 提交支出记录表表单 edit为true时为编辑，默认为新增
	bizExpenditureRecordSubmitForm(data, edit = false) {
		return request(edit ? 'edit' : 'add', data)
	},
	bizExpenditureRecordEdit(data) {
		return request('edit', data)
	},
	bizExpenditureRecordEditAccount(data) {
		return request('edit/account', data)
	},
	// 删除支出记录表
	bizExpenditureRecordDelete(data) {
		return request('delete', data)
	},
	// 获取支出记录表详情
	bizExpenditureRecordDetail(data) {
		return request('detail', data, 'get')
	},
	bizExpenditureRecordList(data) {
		return request('list', data, 'get')
	}
}
