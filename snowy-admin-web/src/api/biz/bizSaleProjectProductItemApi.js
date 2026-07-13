import { baseRequest } from '@/utils/request'

const request = (url, ...arg) => baseRequest(`/biz/saleprojectproductitem/` + url, ...arg)

/**
 * 发货产品关系用于保存套件产品关系Api接口管理器
 *
 * @author 李治磊
 * @date  2024/10/14 13:51
 **/
export default {
	saleProjectProductItemEditMark(data) {
		return request('mark/edit', data, 'post')
	}

	// 获取发货产品关系用于保存套件产品关系详情
}
