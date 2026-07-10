import { Decimal } from 'decimal.js'
import { safeJsonParse } from '@/utils/json'

const decimal = (value) => {
	try {
		return new Decimal(value || 0)
	} catch (error) {
		return new Decimal(0)
	}
}

const addQuantity = (map, productId, amount) => {
	if (!productId) return
	map[productId] = map[productId] || { totalNumber: new Decimal(0) }
	map[productId].totalNumber = map[productId].totalNumber.add(decimal(amount))
}

const addProductQuantities = (map, product, parentAmount) => {
	const children = Array.isArray(product?.children) ? product.children : []
	if (children.length === 0) {
		addQuantity(map, product?.productId, parentAmount)
		return
	}

	children.forEach((child) => {
		const childProduct = safeJsonParse(child.extJson, {}).product || {}
		addQuantity(map, child.targetId || childProduct.id, decimal(child.number).mul(parentAmount))
	})
}

self.onmessage = function (event) {
	const orderProduct = {}
	const totalOutProduct = {}
	const totalReturnProduct = {}
	const { orderList = [], projectlist = [], bizProducts = [] } = event.data || {}
	const productMap = bizProducts.reduce((map, item) => {
		map[item.id] = item
		return map
	}, {})

	orderList.forEach((order) => {
		;(order.orderItems || []).forEach((orderItem) => {
			const productId = orderItem.productId
			if (!productId) return
			orderProduct[productId] = orderProduct[productId] || {
				totalAmount: new Decimal(0),
				totalNumber: new Decimal(0)
			}
			orderProduct[productId].totalAmount = orderProduct[productId].totalAmount.add(decimal(orderItem.amount))
			orderProduct[productId].totalNumber = orderProduct[productId].totalNumber.add(decimal(orderItem.number))
		})
	})

	let totalAmount = new Decimal(0)
	projectlist.forEach((project) => {
		totalAmount = totalAmount.add(decimal(project.totalPrice).sub(decimal(project.rebateAmount)))
		;(project.productList || []).forEach((product) => {
			addProductQuantities(totalOutProduct, product, decimal(product.number))
		})
		;(project.returnOrders || []).forEach((returnOrder) => {
			;(returnOrder.productList || []).forEach((returnItem) => {
				const product = (project.productList || []).find((item) => item.id === returnItem.projectProductItemId)
				if (product) {
					addProductQuantities(totalReturnProduct, product, decimal(returnItem.amount))
				}
			})
		})
	})

	Object.values(orderProduct).forEach((product) => {
		product.unitPrice = product.totalNumber.isZero()
			? new Decimal(0)
			: product.totalAmount.dividedBy(product.totalNumber).toDecimalPlaces(2)
	})

	let totalOrderAmount = new Decimal(0)
	Object.keys(totalOutProduct).forEach((productId) => {
		const purchaseUnitPrice = orderProduct[productId]?.unitPrice || decimal(productMap[productId]?.purchasePrice)
		const returnNumber = totalReturnProduct[productId]?.totalNumber || new Decimal(0)
		const actualNumber = totalOutProduct[productId].totalNumber.sub(returnNumber)
		totalOutProduct[productId].totalAmount = actualNumber.mul(purchaseUnitPrice)
		totalOutProduct[productId].totalReturnAmount = returnNumber
		totalOutProduct[productId].unitPrice = decimal(purchaseUnitPrice)
		totalOrderAmount = totalOrderAmount.add(totalOutProduct[productId].totalAmount)
	})

	const grossProfit = totalAmount.minus(totalOrderAmount)
	const grossProfitLv = totalAmount.isZero()
		? new Decimal(0)
		: grossProfit.dividedBy(totalAmount).times(100).toDecimalPlaces(2)
	const productList = Object.keys(totalOutProduct).map((productId) => {
		const item = totalOutProduct[productId]
		return {
			id: productId,
			productName: productMap[productId]?.productName || productId,
			totalAmount: item.totalAmount.toString(),
			totalReturnAmount: item.totalReturnAmount.toString(),
			totalNumber: item.totalNumber.toString(),
			unitPrice: item.unitPrice.toString()
		}
	})

	self.postMessage({
		cost: totalOrderAmount.toString(),
		salesRevenue: totalAmount.toString(),
		grossProfitLv: grossProfitLv.toString(),
		grossProfit: grossProfit.toString(),
		productList
	})
}
