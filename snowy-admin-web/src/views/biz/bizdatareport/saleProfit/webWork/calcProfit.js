import dayjs from '@/utils/dayjs'
import { Decimal } from 'decimal.js'

self.onmessage = function (event) {
	const orderProduct = {}
	const totalOutProduct = {}
	const totalReturnProduct = {}

	const { orderList, projectlist, bizProducts } = event.data
	const productMap = bizProducts.reduce((map, item) => {
		map[item.id] = item // 将每个对象按 id 存储到 map 中
		return map
	}, {})

	let totalAmount = new Decimal(0)
	orderList.forEach((item) => {
		item.orderItems.forEach((orderItem) => {
			const { productId, amount, number } = orderItem
			if (!orderProduct[productId]) {
				orderProduct[productId] = {
					totalAmount: new Decimal(0),
					totalNumber: new Decimal(0)
				}
			}
			orderProduct[productId].totalAmount = orderProduct[productId].totalAmount.add(new Decimal(amount))
			orderProduct[productId].totalNumber = orderProduct[productId].totalNumber.add(new Decimal(number))
		})
	})
	projectlist.forEach((project) => {
		//扣除回扣
		//总销售款项
		totalAmount = totalAmount.add(
			new Decimal(project.totalPrice ? project.totalPrice : 0).sub(
				new Decimal(project.rebateAmount ? project.rebateAmount : 0)
			)
		)
		project.productList.forEach((product) => {
			if (product.children) {
				product.children.forEach((child) => {
					const { id } = JSON.parse(child.extJson).product
					const number = child.number
					if (!totalOutProduct[id]) {
						totalOutProduct[id] = {
							totalNumber: new Decimal(number).mul(new Decimal(product.number))
						}
					} else {
						totalOutProduct[id].totalNumber = totalOutProduct[id].totalNumber.add(
							new Decimal(number).mul(new Decimal(product.number))
						)
					}
				})
			} else {
				const { number, productId } = product
				if (!totalOutProduct[productId]) {
					totalOutProduct[productId] = {
						totalNumber: new Decimal(number)
					}
				} else {
					totalOutProduct[productId].totalNumber = totalOutProduct[productId].totalNumber.add(new Decimal(number))
				}
			}
		})
		project.returnOrders.forEach((returnOrder) => {
			returnOrder.productList.forEach((item) => {
				const product = project.productList.find((v) => v.id === item.projectProductItemId)
				if (product.children) {
					product.children.forEach((child) => {
						const { id } = JSON.parse(child.extJson).product
						const number = child.number
						if (!totalReturnProduct[id]) {
							totalReturnProduct[id] = {
								totalNumber: new Decimal(number).mul(new Decimal(product.number))
							}
						} else {
							totalReturnProduct[id].totalNumber = totalReturnProduct[id].totalNumber.add(
								new Decimal(number).mul(new Decimal(product.number))
							)
						}
					})
				} else {
					const { number, productId } = product
					if (!totalReturnProduct[productId]) {
						totalReturnProduct[productId] = {
							totalNumber: new Decimal(number)
						}
					} else {
						totalReturnProduct[productId].totalNumber = totalReturnProduct[productId].totalNumber.add(
							new Decimal(number)
						)
					}
				}
			})
		})
	})

	Object.keys(orderProduct).forEach((productId) => {
		//每个产品的平均采购价格
		orderProduct[productId].unitPrice = orderProduct[productId].totalAmount
			.dividedBy(orderProduct[productId].totalNumber)
			.toDecimalPlaces(2)
			.toString()

		orderProduct[productId].totalAmount = orderProduct[productId].totalAmount.toString()
		orderProduct[productId].totalNumber = orderProduct[productId].totalNumber.toString()
	})

	let totalOrderAmount = new Decimal(0)

	Object.keys(totalOutProduct).forEach((id) => {
		if (!orderProduct[id]) {
			orderProduct[id] = {}
			orderProduct[id].unitPrice = productMap[id].purchasePrice ? productMap[id].purchasePrice : 0
		}

		totalOutProduct[id].totalAmount = totalOutProduct[id].totalNumber
			.sub(totalReturnProduct[id] ? totalReturnProduct[id].totalNumber : 0)
			.mul(new Decimal(orderProduct[id].unitPrice))
		totalOutProduct[id].totalReturnAmount = totalReturnProduct[id] ? totalReturnProduct[id].totalNumber : new Decimal(0)
		totalOutProduct[id].unitPrice = new Decimal(orderProduct[id].unitPrice)

		totalOrderAmount = totalOrderAmount.add(totalOutProduct[id].totalAmount)
	})

	const grossProfit = totalAmount.minus(totalOrderAmount).toString()

	const grossProfitLv = new Decimal(grossProfit).dividedBy(totalAmount).times(100).toDecimalPlaces(2).toString()

	const productList = Object.keys(totalOutProduct).map((v) => {
		const object = totalOutProduct[v]
		const product = productMap[v]

		return {
			id: v,
			productName: product.productName, //产品名称
			totalAmount: object.totalAmount.toString(), //总采购额
			totalReturnAmount: object.totalReturnAmount.toString(), //总销售退货数量
			totalNumber: object.totalNumber.toString(), //总出库数量
			unitPrice: object.unitPrice.toString() //平均采购单价
		}
	})

	self.postMessage({
		cost: totalOrderAmount.toString(),
		salesRevenue: totalAmount.toString(),
		grossProfitLv,
		grossProfit,
		productList
	})
}
