import { moduleRequest } from '@/utils/request'

const request = moduleRequest(`/auth/b/safe/`)

/**
 * 二级会话密码验证！
 */
export  default  {
	openSafeByPassword(data){
		return request('password', data, 'post')
	},

}
