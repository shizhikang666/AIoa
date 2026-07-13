import { useLoading } from '@/composables/useLoading'
import bizProductApi from '@/api/biz/bizProductApi'
import { cloneDeep } from 'lodash-es'

export function useProduct() {
	const warpProduct = async (products, key = 'id') => {
		const map = new Map()
		const ids = products.map((v, index) => {
			return { id: v[key] }
		})
		const res = await bizProductApi.bizProductChildren(ids)
		res.forEach((value) => {
			let list = map.get(value.objectId)
			if (!list) {
				list = []
				map.set(value.objectId, list)
			}
			list.push({
				...value.product,
				number: value.number
			})
		})
		const result = products.map((v) => {
			if (map.has(v[key])) {
				return {
					...cloneDeep(v),
					children: map.get(v[key])
				}
			}

			return {
				...cloneDeep(v)
			}
		})
		return result
	}

	return {
		warpProduct
	}
}
