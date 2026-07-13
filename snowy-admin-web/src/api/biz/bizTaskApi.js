import { baseRequest } from '@/utils/request'
import tool from "@/utils/tool";
import sysConfig from "@/config";

const request = (url, ...arg) => baseRequest(`/biz/task/` + url, ...arg)

/**
 * 流程任务表Api接口管理器
 *
 * @author 李治磊
 * @date  2024/08/09 18:01
 **/
export default {
	//获取当前待办流程分页
	bizTaskPage(data) {
		return request('page', data, 'get')
	},
	bizHistoryTaskPage(data) {
		return request('history/page', data, 'get')
	},
	bizTaskList(data) {
		return request('list', data, 'get')
	},
	bizTaskCount() {
		return request('count', {},'get')
	},

	reject(data){
		return request("reject",data,'post')
	},
	approve(data){
		return request("approve",data,'post')
	},
	runtimeActivityDetail(data){

		return request("runtime/activity/detail",data,'get')
	},

	sse(data){
		return request("sse/stream",data,'get')
	},






}
