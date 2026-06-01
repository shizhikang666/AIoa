import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/sys/sysConfig/` + url, ...arg)

/**
 * 系统配置表Api接口管理器
 *
 * @author 李治磊
 * @date  2024/09/18 15:22
 **/
export default {

	sysConfigDetail(data) {
		return request('detail', data, 'get')
	},
	saveConfig(data) {
		return request('edit', data, 'post')
	}
}
