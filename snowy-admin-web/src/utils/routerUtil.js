/**
 *  Copyright [2022] [https://www.xiaonuo.vip]
 *	Snowy采用APACHE LICENSE 2.0开源协议，您在使用过程中，需要注意以下几点：
 *	1.请不要删除和修改根目录下的LICENSE文件。
 *	2.请不要删除和修改Snowy源码头部的版权声明。
 *	3.本项目代码可免费商业使用，商业使用请保留源码和相关描述文件的项目出处，作者声明等。
 *	4.分发源码时候，请注明软件出处 https://www.xiaonuo.vip
 *	5.不可二次分发开源参与同类竞品，如有想法可联系团队xiaonuobase@qq.com商议合作。
 *	6.若您的项目无法满足以上几点，需要更多功能代码，获取Snowy商业授权许可，请在官网购买授权，地址为 https://www.xiaonuo.vip
 */
import userRoutes from '@/config/route'

const isHidden = (menu) => menu?.meta?.hidden === true

const isContainerMenu = (menu) => {
	const type = menu?.meta?.type
	return type === 'catalog' || type === 'module' || menu?.category === 'MODULE'
}

const findFirstAccessibleMenu = (menu) => {
	if (!Array.isArray(menu)) {
		return null
	}

	let fallback = null
	for (const item of menu) {
		if (!item || isHidden(item)) {
			continue
		}

		const children = Array.isArray(item.children) ? item.children : []
		if (children.length > 0) {
			const child = findFirstAccessibleMenu(children)
			if (child) {
				return child
			}
		}

		if (item.path) {
			if (!isContainerMenu(item)) {
				return item
			}
			fallback = fallback || item
		}
	}

	return fallback
}

const getIndexMenu = (menu) => {
	return findFirstAccessibleMenu(menu) || userRoutes.menu[0] || null
}

export default {
	getIndexMenu,
	findFirstAccessibleMenu
}
