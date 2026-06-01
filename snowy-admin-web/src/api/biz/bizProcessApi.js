import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/process/` + url, ...arg)

/**
 * 流程任务表Api接口管理器
 *
 * @author 李治磊
 * @date  2024/08/09 18:01
 **/
export default {
	//获取当前待办流程分页
	bizProcessPage(data) {
		return request('page', data, 'get')
	},
	bizProcessAllPage(data) {
		return request('all/page', data, 'get')
	},

	//查询正在运行的流程
	bizProcessQuery(data) {
		return request('query', data, 'get')
	},

	bizVariable(data) {
		return request('variable', data, 'post')
	},
	bizFileList(data) {
		return request('fileList', data, 'post')
	},
	bizProcessQueryList(data) {
		return request('query/list', data)
	},
	bizProcessProjectRuntimeQueryList(data) {
		return request('project/runtime/query/list', data, 'get')
	},
	bizProcessStartProjectInit(data) {
		return request('project/init/start', data, 'post')
	},
	bizProcessStartProjectPlay(data) {
		return request('project/play/start', data, 'post')
	},

	bizProcessStartProjectDelivery(data) {
		return request('project/delivery/start', data, 'post')
	},
	bizProcessStartProjectReissue(data) {
		return request('project/reissue/start', data, 'post')
	},
	bizProcessReturnProjectProductItem(data) {
		return request('project/return/start', data, 'post')
	},
	//采购流程
	bizProcessStartProcure(data) {
		return request('procure/start', data, 'post')
	},
	bizProcessStartProcureInWareHouse(data) {
		return request('procure/warehouse/start', data, 'post')
	},

	//报销流程
	bizProcessStartReimbursement(data) {
		return request('reimbursement/start', data, 'post')
	},
	//付款申请流程
	bizProcessStartMakePayment(data) {
		return request('makePayment/start', data, 'post')
	},
	//请假流程
	bizProcessStartLeave(data) {
		return request('leave/start', data, 'post')
	},
	bizProcessEditLeave(data) {
		return request('leave/edit', data, 'post')
	},

	//收款流程
	bizProcessStart(data) {
		return request('payment/start', data, 'post')
	},

	//取消流程
	bizProcessCancel(data) {
		return request('cancel', data, 'post')
	},
	bizProcessDetail(data) {
		return request('detail', data, 'get')
	}
}
