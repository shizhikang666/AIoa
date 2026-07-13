import dayjs from '@/utils/dayjs'
import { Decimal } from 'decimal.js'

let flagDebug = true
self.onmessage = function (event) {
	// 判断 date1 是否在 date2 的月份之前或包括这个月份
	function isBeforeOrSameMonth(date1, date2) {
		// 获取 date1 和 date2 的年份和月份
		const year1 = date1.year()
		const month1 = date1.month()
		const year2 = date2.year()
		const month2 = date2.month()

		// 判断 date1 是否在 date2 的月份之前或包括这个月份
		return year1 < year2 || (year1 === year2 && month1 <= month2)
	}

	// 判断 date1 是否在 date2 的月份之后，但不包括这个月份
	function isAfterMonthButNotSame(date1, date2) {
		// 获取 date1 和 date2 的年份和月份
		const year1 = date1.year()
		const month1 = date1.month()
		const year2 = date2.year()
		const month2 = date2.month()

		// 判断 date1 是否在 date2 的月份之后，但不包括这个月份
		if (year1 > year2 || (year1 === year2 && month1 > month2)) {
			return true
		}
		return false
	}

	let calc = function (result, year) {
		let { bizSaleProjects, paymentRecords, org, bizExpenditureRecords, settlementAccounts, bizDebitNotes } = result
		const month = []
		const projectIdMap = {}
		const queryYear = dayjs(year)
		//是否当前年份
		const isCurren = (date) => {
			if ((!date) instanceof dayjs) {
				date = dayjs(date)
			}
			return queryYear.isSame(date, 'year')
		}
		//是否之前年份
		const isPre = (date) => {
			// 如果 date 不是 dayjs 对象，则将其转换为 dayjs 对象
			if (!dayjs.isDayjs(date)) {
				date = dayjs(date)
			}

			// 检查 date 是否在 queryYear 之前
			return date.isBefore(queryYear, 'year')
		}

		//之前的年份的营业额度
		let preYearAmount = new Decimal(0)
		let preYearProject = {}
		//之前年份的账户余额
		let preYearAccountAmount = new Decimal(0)
		let preYearAccountAmountMap = {}

		let preArrears = new Decimal(0)
		//所有的未回款的项目
		const unPayProject = []
		//所有的未回款的个人借条
		const unPayLoan = []

		//每个月成交的项目，及其对应的收款金额
		const monthProject = {}

		//每个月收回的未回款 及其对应的项目
		const monthOldPayProject = []

		//生成12个月份数组
		for (let i = 0; i < 12; i++) {
			monthOldPayProject[i] = []
			month[i] = {
				//每个月的业绩总额
				totalAmount: new Decimal(0), //业绩总额
				totalRebateAmount: new Decimal(0), //每月回扣总额
				//其他营收总额
				otherTotalAmount: new Decimal(0),
				//本月新增未回款
				unPaidPaymentAmount: new Decimal(0),
				//本月收回未回款
				recoveredUnpaidAmounts: new Decimal(0),
				//本月以往未回款回款
				previousUnpaidPayments: new Decimal(0),
				//本月项目本月收款总额
				payAmount: new Decimal(0),
				//本月总未收款
				totalUnpaidAmount: new Decimal(0),
				//本月总开支,不包括账号互转
				totalExpenditure: new Decimal(0),
				totalExpenditureArray: [],
				//本月总收入，不包括账号互转,包括了账户创建的时候的金额
				totalPaymentAmount: new Decimal(0),
				//每个月的余额数组
				balanceAccount: {},
				//每个月的剩余额
				balance: new Decimal(0),
				//每个月的个人借款代收款的还款余额
				loanRepayment: new Decimal(0),
				//每个月的个人借款金额
				loan: new Decimal(0)
			}
		}

		bizSaleProjects.forEach((project) => {
			projectIdMap[project.id] = project
			const completionDate = dayjs(project.completionDate)
			//如果是指定年份的则记录成交额
			if (isCurren(completionDate)) {
				const index = completionDate.month()
				const activeMoth = month[index]
				activeMoth.totalAmount = month[index].totalAmount.add(project.totalPrice)
				activeMoth.totalRebateAmount = month[index].totalRebateAmount.add(
					project.rebateAmount ? project.rebateAmount : 0
				)
				//历史已收款记录本月项目本月收款总额 减去本月项目退款总额
				activeMoth.payAmount = month[index].payAmount.add(project.historyAmount).sub(project.totalReturnAmount)

				if (!monthProject[index]) {
					monthProject[index] = {}
				}
				//归类每个月份的成交的项目
				monthProject[index][project.id] = {
					project,
					paymentRecords: []
				}
			} else {
				let amount = new Decimal(project.totalPrice).sub(project.historyAmount)
				//去年成交的未收款总额 减去历史收款的
				preYearAmount = preYearAmount.add(amount).add(new Decimal(project.totalReturnAmount))

				//去年的所有项目列表，剔除在去年已经全部回款的
				preYearProject[project.id] = {
					project,
					paymentRecords: [],
					amount: amount
				}
			}

			if (project.playState !== 'PAID') {
				const unPayAmount = new Decimal(project.totalPrice).sub(project.amountCollected).toNumber()
				unPayProject.push({
					...project,
					company: org.name,
					unPayAmount,
					year: completionDate.year(),
					month: completionDate.month() + 1
				})
			}
		})
		paymentRecords.forEach((paymentRecord) => {
			const payerTime = dayjs(paymentRecord.payerTime)
			//如果是本年的收款
			if (isCurren(payerTime)) {
				const index = payerTime.month()
				const activeMoth = month[index]

				if (!activeMoth.balanceAccount[paymentRecord.targetId]) {
					activeMoth.balanceAccount[paymentRecord.targetId] = new Decimal(0)
				}
				activeMoth.balanceAccount[paymentRecord.targetId] = activeMoth.balanceAccount[paymentRecord.targetId].add(
					paymentRecord.amount
				)
				//排除往来的计算
				if (paymentRecord.settlementCategory !== 'dealings') {
					//总收款
					activeMoth.totalPaymentAmount = activeMoth.totalPaymentAmount.add(paymentRecord.amount)
				}

				//如果是借款还款
				if (paymentRecord.settlementCategory === 'LoanRepayment') {
					activeMoth.loanRepayment = activeMoth.loanRepayment.add(paymentRecord.amount)
				}

				//如果是项目收款
				if (paymentRecord.settlementCategory === 'PROJECT_PLAY') {
					//如果项目存在
					if (projectIdMap[paymentRecord.objectId]) {
						let completionDate = dayjs(projectIdMap[paymentRecord.objectId].completionDate)
						let completionMonth = month[completionDate.month()]
						//如果收款时间在项目成交时间之前或者项目成交这个月份之内的
						if (isBeforeOrSameMonth(payerTime, completionDate)) {
							//如果是本月的项目则添加为本月项目本月收款总额
							completionMonth.payAmount = completionMonth.payAmount.add(paymentRecord.amount)
							//给每个月的项目添加 每月收款
							monthProject[completionDate.month()][paymentRecord.objectId].paymentRecords.push(paymentRecord)
							//如果收款日期在项目成交日期那个月之后的,不包括成交那个月，则为收回未回款的款项
						} else if (isAfterMonthButNotSame(payerTime, completionDate)) {
							activeMoth.recoveredUnpaidAmounts = activeMoth.recoveredUnpaidAmounts.add(paymentRecord.amount)
							let { projectName, id } = projectIdMap[paymentRecord.objectId]
							monthOldPayProject[payerTime.month()].push({
								...paymentRecord,
								projectName,
								projectId: id
							})
						}
					}

					//如果是以往未回款还款
				} else if (paymentRecord.settlementCategory === 'PreviousUnpaidPayments') {
					activeMoth.previousUnpaidPayments = activeMoth.previousUnpaidPayments.add(paymentRecord.amount)
					let { projectName, id } = { projectName: '以往未回款回款', id: '00' }
					monthOldPayProject[payerTime.month()].push({
						...paymentRecord,
						projectName,
						projectId: id
					})
				} else if (
					paymentRecord.settlementCategory !== 'LoanRepayment' &&
					paymentRecord.settlementCategory !== 'dealings' &&
					paymentRecord.settlementCategory !== 'Collection'
				) {
					activeMoth.otherTotalAmount = activeMoth.otherTotalAmount.add(paymentRecord.amount)
				}
			} else {
				if (!preYearAccountAmountMap[paymentRecord.targetId]) {
					preYearAccountAmountMap[paymentRecord.targetId] = new Decimal(0)
				}

				//如果是借款还款
				if (paymentRecord.settlementCategory === 'LoanRepayment') {
					preArrears = preArrears.sub(paymentRecord.amount)
				}

				if (paymentRecord.settlementCategory === 'PROJECT_PLAY') {
					//如果项目成交日期是今年，但是收款记录不是今年，提前收的款项
					if (projectIdMap[paymentRecord.objectId] && isCurren(projectIdMap[paymentRecord.objectId].completionDate)) {
						let completionDate = dayjs(projectIdMap[paymentRecord.objectId].completionDate)
						let completionMonth = month[completionDate.month()]
						completionMonth.payAmount = completionMonth.payAmount.add(paymentRecord.amount)
						//给这个月的项目添加 每月收款
						monthProject[completionDate.month()][paymentRecord.objectId].paymentRecords.push(paymentRecord)

						//如果是之前的项目，
					} else if (
						projectIdMap[paymentRecord.objectId] &&
						isPre(projectIdMap[paymentRecord.objectId].completionDate)
					) {
						preYearAmount = preYearAmount.sub(paymentRecord.amount)
						if (preYearProject[paymentRecord.objectId]) {
							preYearProject[paymentRecord.objectId].amount = preYearProject[paymentRecord.objectId].amount.sub(
								paymentRecord.amount
							)
						}
					}
				}

				//不是往来计入当前之前总收入
				if (paymentRecord.settlementCategory !== 'dealings') {
					preYearAccountAmount = preYearAccountAmount.add(paymentRecord.amount)
				}

				preYearAccountAmountMap[paymentRecord.targetId] = preYearAccountAmountMap[paymentRecord.targetId].add(
					paymentRecord.amount
				)
			}
		})

		bizExpenditureRecords.forEach((record) => {
			const payerTime = dayjs(record.payerTime)
			//如果是本年的的支出，则归类支出总计
			if (isCurren(payerTime)) {
				const index = payerTime.month()
				const activeMoth = month[index]

				//用于区分每月账号余额
				if (!activeMoth.balanceAccount[record.targetId]) {
					activeMoth.balanceAccount[record.targetId] = new Decimal(0)
				}
				activeMoth.balanceAccount[record.targetId] = activeMoth.balanceAccount[record.targetId].sub(record.amount)
				if (record.settlementCategory !== 'dealings') {
					activeMoth.totalExpenditure = activeMoth.totalExpenditure.add(record.amount)
					activeMoth.totalExpenditureArray.push(record)
				}
			} else {
				if (!preYearAccountAmountMap[record.targetId]) {
					preYearAccountAmountMap[record.targetId] = new Decimal(0)
				}
				preYearAccountAmountMap[record.targetId] = preYearAccountAmountMap[record.targetId].sub(record.amount)
				//不是往来的话减少之前总收入
				if (record.settlementCategory !== 'dealings') {
					preYearAccountAmount = preYearAccountAmount.sub(record.amount)
				}
			}
		})
		settlementAccounts.forEach((account) => {
			const createTime = dayjs(account.createTime)
			if (isCurren(createTime)) {
				const index = createTime.month()
				const activeMoth = month[index]
				activeMoth.totalPaymentAmount = activeMoth.totalPaymentAmount.add(account.initialAmount)

				if (!activeMoth.balanceAccount[account.id]) {
					activeMoth.balanceAccount[account.id] = new Decimal(0)
				}
				activeMoth.balanceAccount[account.id] = activeMoth.balanceAccount[account.id].add(account.initialAmount)
			} else if (isPre(createTime)) {
				preYearAccountAmount = preYearAccountAmount.add(account.initialAmount)
				if (!preYearAccountAmountMap[account.id]) {
					preYearAccountAmountMap[account.id] = new Decimal(0)
				}
				preYearAccountAmountMap[account.id] = preYearAccountAmountMap[account.id].add(account.initialAmount)
			}
		})
		bizDebitNotes.forEach((debitNote) => {
			const payerTime = dayjs(debitNote.createTime)
			const endTime = dayjs(debitNote.updateTime)
			if (isCurren(payerTime)) {
				const index = payerTime.month()
				const activeMoth = month[index]
				activeMoth.loan = activeMoth.loan.add(debitNote.amount).sub(debitNote.historyAmount)
			} else {
				preArrears = preArrears.add(debitNote.amount).sub(debitNote.historyAmount)
			}

			if (debitNote.playStatus !== 'AlreadySettled') {
				const unPayAmount = new Decimal(debitNote.amount).sub(debitNote.settlementAmount).toNumber()
				unPayLoan.push({
					...debitNote,
					company: org.name,
					unPayAmount,
					year: payerTime.year(),
					month: payerTime.month() + 1
				})
				//如果是标记已结算的
			} else if (debitNote.amount !== debitNote.settlementAmount) {
				const unPayAmount = new Decimal(debitNote.amount).sub(debitNote.settlementAmount).toNumber()

				month[endTime.month()].loanRepayment = month[endTime.month()].loanRepayment.add(unPayAmount)
			}
		})

		// 上个月 /年度 欠款明细（外面定义一次，初始化一次）
		let preUnpaidProjectDetail = []
		Object.keys(preYearProject).forEach((key) => {
			const project = preYearProject[key]
			if (project.amount.toNumber() <= 0) {
				delete preYearProject[key]
				return
			}

			preUnpaidProjectDetail.push({
				id: key,
				name: project.project.projectName,
				project: project.project,
				amount: project.amount.toNumber()
			})
		})

		month.forEach((month, index) => {
			//本月新增未回款等于本月总营业额减去本月项目本月收款总额
			month.unPaidPaymentAmount = month.totalAmount.sub(month.payAmount)
			//在这个月的未回款等于上个月（年度）总未回款减去本月回款额度再添加本月新增的未回款

			month.totalUnpaidAmount = preYearAmount.sub(month.recoveredUnpaidAmounts).add(month.unPaidPaymentAmount)

			month.recoveredUnpaidAmounts = month.recoveredUnpaidAmounts.add(month.previousUnpaidPayments)

			//账户余额等于去年或者上个月的账户余额减去每个月的开支加上每个月的收入
			month.balance = preYearAccountAmount.add(month.totalPaymentAmount.sub(month.totalExpenditure))
			const keys = [...Object.keys(preYearAccountAmountMap), ...Object.keys(month.balanceAccount)]
			const baseAmount = {}
			//账户余额详情，去重key了
			new Set(keys).forEach((key) => {
				baseAmount[key] = new Decimal(0)

				if (preYearAccountAmountMap[key]) {
					baseAmount[key] = baseAmount[key].add(preYearAccountAmountMap[key])
				}
				if (month.balanceAccount[key]) {
					baseAmount[key] = baseAmount[key].add(month.balanceAccount[key])
				}
			})

			preYearAccountAmountMap = baseAmount
			month.balanceAccount = Object.keys(baseAmount).map((key) => {
				const find = settlementAccounts.find((account) => {
					return account.id === key
				})

				return {
					currentMonthAmount: baseAmount[key].toString(),
					...find
				}
			})
			//借款
			month.loan = preArrears.add(month.loan.sub(month.loanRepayment))
			//本月销售项目本月收款
			month.projectList = monthProject[index] ? Object.values(monthProject[index]) : []
			//本月收回未回款记录
			month.monthOldPayProject = monthOldPayProject[index] ? monthOldPayProject[index] : []
			preArrears = month.loan
			preYearAccountAmount = month.balance
			preYearAmount = month.totalUnpaidAmount
			Object.keys(month).forEach((key) => {
				if (month[key] instanceof Decimal) {
					month[key] = month[key].toString()
				}
			})

			// 复制剩余的的欠款明细
			let currentUnpaidDetail = preUnpaidProjectDetail.map((item) => ({ ...item }))

			// 1. 本月新增未回款（本月成交的项目未收部分）
			if (month.projectList) {
				month.projectList.forEach(({ project, paymentRecords }) => {
					let amountCollected = new Decimal(0)
					paymentRecords.forEach((paymentRecord) => {
						amountCollected = amountCollected.add(paymentRecord.amount)
					})

					const unPayAmount = new Decimal(project.totalPrice).sub(project.historyAmount).sub(amountCollected).toNumber()

					if (unPayAmount > 0) {
						currentUnpaidDetail.push({
							id: project.id,
							name: project.projectName,
							project: project,
							amount: unPayAmount
						})
					}
				})
			}
			// 2. 扣掉本月收回的历史回款
			if (month.monthOldPayProject) {
				month.monthOldPayProject.forEach((pay) => {
					const idx = currentUnpaidDetail.findIndex((item) => item.id === pay.projectId)
					if (idx >= 0) {
						currentUnpaidDetail[idx].amount = new Decimal(currentUnpaidDetail[idx].amount)
							.sub(new Decimal(pay.amount))
							.toNumber()

						if (currentUnpaidDetail[idx].amount <= 0) {
							currentUnpaidDetail.splice(idx, 1) // 清除已结清项目
						}
					}
				})
			}

			// 保存到 month
			month.unPaidProjectDetail = [...currentUnpaidDetail]
			// 更新滚动欠款
			preUnpaidProjectDetail = currentUnpaidDetail
		})
		return {
			org,
			month: month,
			unPayProject,
			unPayLoan
		}
	}
	let { result, year } = event.data
	self.postMessage(calc(result, year))
}
